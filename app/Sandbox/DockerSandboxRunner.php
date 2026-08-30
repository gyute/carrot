<?php

namespace App\Sandbox;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs each script in a throwaway container: no network (unless the tool was
 * approved with internet access), read-only root,
 * unprivileged user, every capability dropped, memory/CPU/pid ceilings, and
 * the work directory mounted read-only. The worker kills the container when
 * the tool's timeout passes.
 */
final class DockerSandboxRunner implements SandboxRunner
{
    private bool $ready = false;

    /**
     * @param  array{php: string, shell: string}  $images
     */
    public function __construct(
        private string $binary,
        private array $images,
        private string $workdirBase,
        private int $outputBytes,
        private string $cpus,
        private int $pids,
        private bool $requireRootless,
    ) {}

    public function ensureReady(): void
    {
        if ($this->ready) {
            return;
        }

        $info = new Process([$this->binary, 'info', '--format', '{{json .SecurityOptions}}']);
        $info->setTimeout(15)->run();

        if (! $info->isSuccessful()) {
            throw new RuntimeException("Sandbox: `{$this->binary} info` failed: ".trim($info->getErrorOutput()));
        }

        if ($this->requireRootless && ! str_contains($info->getOutput(), 'rootless')) {
            throw new RuntimeException('Sandbox: the Docker daemon is not rootless. Refusing to run user code against a root dockerd (set SANDBOX_REQUIRE_ROOTLESS=false to override on a development box).');
        }

        $this->ready = true;
    }

    public function run(RunSpec $spec): RunResult
    {
        $this->ensureReady();

        $workdir = Workdir::create($spec, $this->workdirBase);
        $name = 'carrot-run-'.$spec->id;

        $stdout = new OutputBuffer($this->outputBytes);
        $stderr = new OutputBuffer($this->outputBytes);

        $process = new Process($this->command($spec, $workdir, $name));
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
            $this->kill($name);
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
     * The full `docker run` invocation. Public so a test can pin the flags:
     * a missing one here is a hole in the sandbox.
     *
     * @return array<int, string>
     */
    public function command(RunSpec $spec, Workdir $workdir, string $name): array
    {
        $memory = "{$spec->memoryMb}m";

        return [
            $this->binary, 'run', '--rm', '--interactive=false',
            '--name', $name,
            '--network', $spec->hasNetwork() ? (string) config('sandbox.internet_network', 'bridge') : 'none',
            '--read-only',
            '--tmpfs', '/tmp:rw,noexec,nosuid,size=64m',
            '--user', '65534:65534',
            '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges',
            '--pids-limit', (string) $this->pids,
            '--memory', $memory,
            '--memory-swap', $memory,
            '--cpus', $this->cpus,
            '--ulimit', 'nofile=256:256',
            '--ulimit', 'fsize=10485760',
            '--stop-timeout', '1',
            '--volume', "{$workdir->path}:/work:ro",
            '--workdir', '/work',
            '--env', 'TOOL_INPUTS=/work/inputs.json',
            '--env', 'TOOL_TIMEOUT='.$spec->timeoutSec,
            $this->images[$spec->runtime === 'php' ? 'php' : 'shell'],
            'timeout', '-s', 'KILL', (string) $spec->timeoutSec,
            ...$spec->command(),
        ];
    }

    private function kill(string $name): void
    {
        $kill = new Process([$this->binary, 'kill', $name]);
        $kill->setTimeout(10)->run();
    }

    /**
     * @return array<string, string>
     */
    public function runtimeLabels(): array
    {
        return array_map('strval', (array) config('sandbox.runtimes', []));
    }
}
