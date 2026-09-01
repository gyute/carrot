<?php

use App\Enums\ToolRunStatus;
use App\Events\ToolRunUpdated;
use App\Jobs\RunToolJob;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Sandbox\FakeSandboxRunner;
use App\Sandbox\RunResult;
use App\Sandbox\SandboxRunner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->runner = new FakeSandboxRunner;
    $this->app->instance(SandboxRunner::class, $this->runner);
});

test('a script tool runs through the sandbox and records its output', function () {
    Event::fake([ToolRunUpdated::class]);

    $tool = Tool::factory()->script()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tools.runs.store', $tool));

    $run = ToolRun::query()->sole();
    $response->assertRedirect(route('tools.runs.show', [$tool, $run]));

    expect($run->status)->toBe(ToolRunStatus::Completed)
        ->and($run->stdout)->toBe("fake: php\n")
        ->and($run->exit_code)->toBe(0)
        ->and($run->source_hash)->toBe($tool->source_hash)
        ->and($this->runner->lastSpec()?->source)->toBe($tool->source)
        ->and($this->runner->lastSpec()?->timeoutSec)->toBe(30);

    Event::assertDispatchedTimes(ToolRunUpdated::class, 2);

    $this->actingAs($user)
        ->get(route('tools.runs.show', [$tool, $run]))
        ->assertInertia(fn ($page) => $page
            ->component('tools/runs/show')
            ->where('run.status', 'completed')
            ->where('run.stdout', "fake: php\n")
        );
});

test('inputs are validated against the tool schema and unknown keys dropped', function () {
    $tool = Tool::factory()->script()->create([
        'config' => [
            'runtime' => 'shell',
            'timeout_sec' => 10,
            'memory_mb' => 64,
            'inputs' => [
                ['key' => 'day', 'label' => '日付', 'type' => 'text', 'required' => true],
                ['key' => 'mode', 'label' => 'モード', 'type' => 'select', 'required' => false, 'options' => ['fast', 'full']],
                ['key' => 'n', 'label' => '件数', 'type' => 'number', 'required' => false],
            ],
        ],
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('tools.show', $tool))
        ->post(route('tools.runs.store', $tool), ['inputs' => ['mode' => 'nope', 'n' => 'x']])
        ->assertSessionHasErrors(['day', 'mode', 'n']);

    $this->actingAs($user)
        ->post(route('tools.runs.store', $tool), ['inputs' => ['day' => '2026-08-27', 'mode' => 'fast', 'n' => '3', 'extra' => 'no']])
        ->assertRedirect();

    // jsonb does not keep the key order it was handed, so sort before an
    // identity comparison.
    $inputs = ToolRun::query()->sole()->inputs;
    ksort($inputs);

    expect($inputs)->toBe(['day' => '2026-08-27', 'mode' => 'fast', 'n' => '3'])
        ->and($this->runner->lastSpec()?->runtime)->toBe('shell');
});

test('only running script tools can be run', function () {
    $link = Tool::factory()->create();
    $retired = Tool::factory()->script()->deprecated()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tools.runs.store', $link))->assertForbidden();
    $this->actingAs($user)->post(route('tools.runs.store', $retired))->assertForbidden();
});

test('a run is only visible to who started it, or an admin', function () {
    $run = ToolRun::factory()->completed()->create();

    $this->actingAs(User::factory()->create())->get(route('tools.runs.show', [$run->tool, $run]))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('tools.runs.show', [$run->tool, $run]))->assertOk();
    $this->actingAs($run->user)->get(route('tools.runs.show', [$run->tool, $run]))->assertOk();
});

test('the job refuses to run source that no longer matches the approved hash', function () {
    $tool = Tool::factory()->script()->create();
    $run = ToolRun::factory()->for($tool)->create(['source_hash' => hash('sha256', 'something else')]);

    (new RunToolJob($run))->handle($this->runner);

    expect($run->fresh()?->status)->toBe(ToolRunStatus::Failed)
        ->and($run->fresh()?->error_message)->toContain('refusing')
        ->and($this->runner->specs)->toBe([]);
});

test('timeouts and non-zero exits are recorded as such', function () {
    $tool = Tool::factory()->script()->create();
    $user = User::factory()->create();

    $this->runner->willReturn(new RunResult(null, 'partial', '', 30_000, timedOut: true));
    $this->actingAs($user)->post(route('tools.runs.store', $tool));

    $this->runner->willReturn(new RunResult(2, '', "boom\n", 5, truncated: true));
    $this->actingAs($user)->post(route('tools.runs.store', $tool));

    $statuses = ToolRun::query()->oldest('id')->pluck('status')->map(fn ($status) => $status->value)->all();

    expect($statuses)->toBe(['timed_out', 'failed'])
        ->and(ToolRun::query()->latest('id')->first()?->truncated)->toBeTrue();
});

test('runs are rate limited per user and queued on the sandbox queue', function () {
    Queue::fake();
    config(['sandbox.rate_limit_per_minute' => 2]);

    $tool = Tool::factory()->script()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tools.runs.store', $tool))->assertRedirect();
    $this->actingAs($user)->post(route('tools.runs.store', $tool))->assertRedirect();
    $this->actingAs($user)->post(route('tools.runs.store', $tool))->assertStatus(429);

    Queue::assertPushed(RunToolJob::class, 2);
    Queue::assertPushedOn('sandbox', RunToolJob::class);
});

test('an admin test-runs a submitted script before approving it', function () {
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->script("echo test\n", 'shell')->pending()->create();

    $this->actingAs($submission->user)->post(route('admin.approvals.test-run', $submission))->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.approvals.test-run', $submission))
        ->assertRedirect(route('admin.approvals.show', $submission));

    $run = ToolRun::query()->sole();

    expect($run->tool_id)->toBeNull()
        ->and($run->tool_submission_id)->toBe($submission->id)
        ->and($run->status)->toBe(ToolRunStatus::Completed)
        ->and($this->runner->lastSpec()?->source)->toBe("echo test\n");

    $this->actingAs($admin)
        ->get(route('admin.approvals.show', $submission))
        ->assertInertia(fn ($page) => $page->has('testRuns', 1)->where('can.testRun', true));

    $link = ToolSubmission::factory()->pending()->create();
    $this->actingAs($admin)->post(route('admin.approvals.test-run', $link))->assertStatus(422);
});

test('forms and runs report the runtime the driver actually uses', function () {
    config(['sandbox.runtimes' => ['php' => 'PHP 8.3 (php:8.3-cli-alpine)', 'shell' => 'Shell (alpine)']]);
    $tool = Tool::factory()->script()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tools.submissions.create'))
        ->assertInertia(fn ($page) => $page->where('limits.runtimes.php', 'PHP 8.3 (php:8.3-cli-alpine)'));

    $this->actingAs($user)->post(route('tools.runs.store', $tool));
    $run = ToolRun::query()->sole();

    $this->actingAs($user)
        ->get(route('tools.runs.show', [$tool, $run]))
        ->assertInertia(fn ($page) => $page->where('run.runtimeLabel', 'PHP 8.3 (php:8.3-cli-alpine)'));
});

test('network access is off unless the tool was approved with it', function () {
    $tool = Tool::factory()->script()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tools.runs.store', $tool));
    expect($this->runner->lastSpec()?->hasNetwork())->toBeFalse();

    $tool->forceFill(['config' => [...$tool->config, 'network' => 'internet']])->save();
    $this->actingAs($user)->post(route('tools.runs.store', $tool));
    expect($this->runner->lastSpec()?->hasNetwork())->toBeTrue();
});

test('the network choice is validated and shown on the request', function () {
    $user = User::factory()->create();
    $payload = ['kind' => 'script', 'name' => 'n', 'summary' => 's', 'icon' => 'terminal', 'accent' => 'sky', 'config' => ['runtime' => 'php', 'timeout_sec' => 5, 'memory_mb' => 64, 'network' => 'wifi'], 'source' => '<?php'];

    $this->actingAs($user)->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), $payload)
        ->assertSessionHasErrors('config.network');

    $payload['config']['network'] = 'internet';
    $this->actingAs($user)->post(route('tools.submissions.store'), $payload)->assertSessionDoesntHaveErrors();

    expect(ToolSubmission::query()->sole()->payload['config']['network'])->toBe('internet');
});
