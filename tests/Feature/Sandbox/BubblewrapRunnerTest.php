<?php

use App\Sandbox\BubblewrapSandboxRunner;
use App\Sandbox\RunSpec;
use Symfony\Component\Process\ExecutableFinder;

/*
 * Exercises the real bubblewrap driver when the host has it. Skipped
 * elsewhere, so CI without bwrap stays green.
 */
beforeEach(function () {
    if ((new ExecutableFinder)->find('bwrap') === null) {
        $this->markTestSkipped('bwrap is not installed.');
    }

    $this->runner = new BubblewrapSandboxRunner(sys_get_temp_dir().'/carrot-bwrap-test', 4096);

    try {
        $this->runner->ensureReady();
    } catch (RuntimeException $e) {
        $this->markTestSkipped($e->getMessage());
    }
});

test('a script reads its inputs and prints to stdout', function () {
    $result = $this->runner->run(new RunSpec('bw1', 'shell', 'jq -r .name "$TOOL_INPUTS" 2>/dev/null || cat "$TOOL_INPUTS"', ['name' => 'carrot'], 10, 64));

    expect($result->exitCode)->toBe(0)->and(trim($result->stdout))->toContain('carrot');
});

test('the sandbox has no network and cannot write outside /tmp', function () {
    $result = $this->runner->run(new RunSpec('bw2', 'shell', 'touch /tmp/work/x 2>/dev/null && echo WROTE; touch /usr/x 2>/dev/null && echo WROTE; cat /proc/net/dev; echo ok > /tmp/f && cat /tmp/f', [], 10, 64));

    expect($result->stdout)->not->toContain('WROTE')
        ->and($result->stdout)->not->toContain('eth0')
        ->and($result->stdout)->toContain('lo:')
        ->and($result->stdout)->toContain('ok');
});

test('php runs with its inputs and cannot see the worker home', function () {
    $home = getenv('HOME');
    $result = $this->runner->run(new RunSpec('bw4', 'php', '<?php $i = json_decode(file_get_contents(getenv("TOOL_INPUTS")), true); echo $i["name"], " ", count(glob("'.$home.'/.*")) <= 2 ? "hidden" : "visible";', ['name' => 'carrot'], 10, 256));

    expect($result->exitCode)->toBe(0)->and($result->stdout)->toBe('carrot hidden');
});

test('a runaway script is killed at the timeout', function () {
    $result = $this->runner->run(new RunSpec('bw3', 'shell', 'sleep 30; echo late', [], 1, 64));

    expect($result->stdout)->not->toContain('late')
        ->and($result->timedOut || $result->exitCode !== 0)->toBeTrue()
        ->and($result->durationMs)->toBeLessThan(10_000);
});
