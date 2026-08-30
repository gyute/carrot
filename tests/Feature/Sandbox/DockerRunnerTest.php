<?php

use App\Sandbox\DockerSandboxRunner;
use App\Sandbox\RunSpec;

/*
 * Runs against a real (rootless) Docker with the sandbox images built.
 * Opt in with SANDBOX_DOCKER_TESTS=1; skipped otherwise.
 */
beforeEach(function () {
    if (! env('SANDBOX_DOCKER_TESTS')) {
        $this->markTestSkipped('Set SANDBOX_DOCKER_TESTS=1 to run against Docker.');
    }

    $this->runner = new DockerSandboxRunner(
        binary: (string) config('sandbox.binary'),
        images: config('sandbox.images'),
        workdirBase: sys_get_temp_dir().'/carrot-docker-test',
        outputBytes: 4096,
        cpus: '0.5',
        pids: 64,
        requireRootless: (bool) config('sandbox.require_rootless'),
    );
    $this->runner->ensureReady();
});

test('php runs as nobody with no network', function () {
    $result = $this->runner->run(new RunSpec('dk1', 'php', '<?php echo posix_geteuid(), " ", @file_get_contents("http://example.com") === false ? "offline" : "online";', [], 10, 128));

    expect($result->exitCode)->toBe(0)->and($result->stdout)->toBe('65534 offline');
});

test('the root filesystem is read-only and a runaway script is killed', function () {
    $write = $this->runner->run(new RunSpec('dk2', 'shell', 'touch /etc/x 2>&1; touch /work/x 2>&1; echo done', [], 10, 64));
    expect($write->stdout)->toContain('Read-only')->toContain('done');

    $slow = $this->runner->run(new RunSpec('dk3', 'shell', 'sleep 30; echo late', [], 1, 64));
    expect($slow->stdout)->not->toContain('late')->and($slow->durationMs)->toBeLessThan(15_000);
});
