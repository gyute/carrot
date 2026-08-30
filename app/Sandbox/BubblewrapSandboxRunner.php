<?php

namespace App\Sandbox;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A development stand-in for the Docker runner on a machine with bubblewrap
 * but no rootless Docker (a WSL box, say). Same shape of isolation - fresh
 * namespaces, no network, read-only root, private /tmp, work directory
 * read-only - but it runs the host's own php/sh, memory is bounded with
 * ulimit rather than a cgroup, and there is no process limit. Not for
 * production.
 */
final class BubblewrapSandboxRunner implements SandboxRunner
{
    /** @var array<string, string> Absolute paths of the host binaries used. */
    private array $binaries = [];

    public function __construct(
        private string $workdirBase,
        private int $outputBytes,
    ) {}

    public function ensureReady(): void
    {
        if ($this->binaries !== []) {
            return;
        }

        $finder = new ExecutableFinder;

        foreach (['bwrap', 'php', 'sh', 'timeout'] as $binary) {
            $path = $finder->find($binary);

            if ($path === null) {
                throw new RuntimeException("Sandbox: `{$binary}` is not installed on this host.");
            }

            $this->binaries[$binary] = $path;
        }
    }

    public function run(RunSpec $spec): RunResult
    {
        $this->ensureReady();

        $workdir = Workdir::create($spec, $this->workdirBase);
        $stdout = new OutputBuffer($this->outputBytes);
        $stderr = new OutputBuffer($this->outputBytes);

        $process = new Process($this->command($spec, $workdir));
        $process->setTimeout($spec->timeoutSec + 5);
        $process->setIdleTimeout(null);

        $started = hrtime(true);
        $timedOut = false;

        try {
            $process->run(function (string $type, string $chunk) use ($stdout, $stderr): void {
                ($type === Process::OUT ? $stdout : $stderr)->append($chunk);
            });
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } finally {
            $workdir->remove();
        }

        return new RunResult(
            exitCode: $timedOut ? null : $process->getExitCode(),
            stdout: $stdout->contents(),
            stderr: $stderr->contents(),
            durationMs: (int) ((hrtime(true) - $started) / 1_000_000),
            timedOut: $timedOut,
            truncated: $stdout->truncated() || $stderr->truncated(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function command(RunSpec $spec, Workdir $workdir): array
    {
        $this->ensureReady();

        // ulimit -v bounds address space, which the interpreter's shared
        // libraries alone push past a small tool limit; give it fixed headroom
        // and let php's own memory_limit (set in RunSpec::command) do the
        // real bounding.
        $kb = ($spec->memoryMb + 512) * 1024;

        // Absolute paths: the host's php may live somewhere PATH inside the
        // sandbox does not cover (a Homebrew prefix, say).
        $command = $spec->command('/tmp/work');
        $command[0] = $this->binaries[$command[0]] ?? $command[0];
        $inner = implode(' ', array_map(escapeshellarg(...), $command));

        // dash's ulimit takes one option per call.
        $limits = "ulimit -v {$kb}; ulimit -f 10240;";

        return [
            $this->binaries['bwrap'],
            '--unshare-all',
            ...($spec->hasNetwork() ? ['--share-net'] : []),
            '--die-with-parent',
            '--new-session',
            '--ro-bind', '/', '/',
            '--dev', '/dev',
            '--proc', '/proc',
            '--tmpfs', '/sys',
            // The root is bound read-only, so the work directory has to sit
            // under the private /tmp: bwrap cannot create a mountpoint on a
            // read-only tree.
            '--tmpfs', '/tmp',
            ...$this->maskedHome(),
            '--ro-bind', $workdir->path, '/tmp/work',
            '--chdir', '/tmp/work',
            '--clearenv',
            '--setenv', 'PATH', '/usr/local/bin:/usr/bin:/bin',
            '--setenv', 'HOME', '/tmp',
            '--setenv', 'TOOL_INPUTS', '/tmp/work/inputs.json',
            '--setenv', 'TOOL_TIMEOUT', (string) $spec->timeoutSec,
            $this->binaries['sh'], '-c', "{$limits} exec {$this->binaries['timeout']} -s KILL {$spec->timeoutSec} {$inner}",
        ];
    }

    /**
     * Hide the worker's own home directory (dotfiles, keys) without hiding
     * every home - a per-user toolchain like Homebrew may live under /home.
     *
     * @return array<int, string>
     */
    private function maskedHome(): array
    {
        $home = getenv('HOME');

        return is_string($home) && $home !== '' && $home !== '/' && is_dir($home)
            ? ['--tmpfs', $home]
            : [];
    }

    /**
     * The host's own interpreters, since that is what this driver runs.
     *
     * @return array<string, string>
     */
    public function runtimeLabels(): array
    {
        $this->ensureReady();

        $php = new Process([$this->binaries['php'], '-r', 'echo PHP_VERSION;']);
        $php->setTimeout(5)->run();
        $phpVersion = $php->isSuccessful() ? trim($php->getOutput()) : '?';

        return [
            'php' => "PHP {$phpVersion} (開発用 bubblewrap: ホストの php)",
            'shell' => 'Shell - ホストの sh + coreutils (開発用 bubblewrap)',
        ];
    }
}
