<?php

use App\Sandbox\DockerSandboxRunner;
use App\Sandbox\OutputBuffer;
use App\Sandbox\RunSpec;
use App\Sandbox\Workdir;

function dockerRunner(): DockerSandboxRunner
{
    return new DockerSandboxRunner(
        binary: 'docker',
        images: ['php' => 'carrot-sandbox-php:8.3', 'shell' => 'carrot-sandbox-shell:3.20'],
        workdirBase: sys_get_temp_dir().'/carrot-sandbox-test',
        outputBytes: 1024,
        cpus: '0.5',
        pids: 64,
        requireRootless: true,
    );
}

test('the docker command carries every isolation flag', function () {
    $spec = new RunSpec('01run', 'php', '<?php echo 1;', ['a' => 1], 20, 128);
    $workdir = Workdir::create($spec, sys_get_temp_dir().'/carrot-sandbox-test');

    try {
        $command = dockerRunner()->command($spec, $workdir, 'carrot-run-01run');
    } finally {
        $workdir->remove();
    }

    $line = implode(' ', $command);

    expect($line)
        ->toContain('--network none')
        ->toContain('--read-only')
        ->toContain('--user 65534:65534')
        ->toContain('--cap-drop ALL')
        ->toContain('--security-opt no-new-privileges')
        ->toContain('--pids-limit 64')
        ->toContain('--memory 128m --memory-swap 128m')
        ->toContain('--cpus 0.5')
        ->toContain(':/work:ro')
        ->toContain('--tmpfs /tmp:rw,noexec,nosuid,size=64m')
        ->toContain('carrot-sandbox-php:8.3 timeout -s KILL 20 php')
        ->and($command[0])->toBe('docker')
        ->and($command)->not->toContain('--privileged');
});

test('the shell runtime uses the shell image and entrypoint', function () {
    $spec = new RunSpec('01sh', 'shell', 'echo hi', [], 5, 64);
    $workdir = Workdir::create($spec, sys_get_temp_dir().'/carrot-sandbox-test');

    try {
        $command = dockerRunner()->command($spec, $workdir, 'x');

        expect(file_get_contents($workdir->path.'/main.sh'))->toBe('echo hi')
            ->and(file_get_contents($workdir->path.'/inputs.json'))->toBe('[]');
    } finally {
        $workdir->remove();
    }

    expect(implode(' ', $command))->toContain('carrot-sandbox-shell:3.20 timeout -s KILL 5 sh /work/main.sh')
        ->and(is_dir($workdir->path))->toBeFalse();
});

test('output past the cap is dropped and marked', function () {
    $buffer = new OutputBuffer(10);
    $buffer->append('12345');
    $buffer->append('678901234');
    $buffer->append('more');

    expect($buffer->truncated())->toBeTrue()
        ->and($buffer->contents())->toBe("1234567890\n…(truncated)");
});

test('internet access swaps only the network flag', function () {
    $spec = new RunSpec('01net', 'php', '<?php', [], 5, 64, RunSpec::NETWORK_INTERNET);
    $workdir = Workdir::create($spec, sys_get_temp_dir().'/carrot-sandbox-test');

    try {
        $line = implode(' ', dockerRunner()->command($spec, $workdir, 'x'));
    } finally {
        $workdir->remove();
    }

    expect($line)->toContain('--network bridge')->not->toContain('--network none')
        ->toContain('--read-only')->toContain('--cap-drop ALL');
});
