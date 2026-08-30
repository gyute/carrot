<?php

use App\Models\ToolRun;
use App\Models\User;
use App\Sandbox\SandboxRunner;
use App\Support\SystemStatus;
use Illuminate\Support\Facades\DB;

test('the system screen is for admins only', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.system.index'))->assertForbidden();
});

test('the system screen reports queues, workers, sandbox, reverb, runs and log', function () {
    config(['sandbox.driver' => 'fake', 'broadcasting.default' => 'null']);

    SystemStatus::heartbeat('default');
    DB::table('jobs')->insert(['queue' => 'sandbox', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(2)->getTimestamp(), 'created_at' => now()->getTimestamp()]);
    DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'database', 'queue' => 'sandbox', 'payload' => json_encode(['displayName' => 'App\\Jobs\\RunToolJob']), 'exception' => "RuntimeException: boom\nstack", 'failed_at' => now()]);
    ToolRun::factory()->completed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system/index')
            ->where('status.queues.0.name', 'default')
            ->where('status.queues.0.alive', true)
            ->where('status.queues.1.name', 'sandbox')
            ->where('status.queues.1.alive', false)
            ->where('status.queues.1.pending', 1)
            ->where('status.sandbox.ready', true)
            ->where('status.reverb.up', null)
            ->where('status.failedJobs.count', 1)
            ->where('status.failedJobs.recent.0.job', 'App\\Jobs\\RunToolJob')
            ->where('status.failedJobs.recent.0.exception', 'RuntimeException: boom')
            ->has('status.runs.recent', 1)
            ->where('status.runs.last24h.completed', 1)
        );
});

test('a null sandbox driver is reported as not ready with its reason', function () {
    config(['sandbox.driver' => 'none']);
    app()->forgetInstance(SandboxRunner::class);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page
            ->where('status.sandbox.ready', false)
            ->where('status.sandbox.message', fn ($message) => str_contains($message, 'none'))
        );
});

test('the log tail follows the configured channel instead of assuming a path', function () {
    $path = storage_path('logs/system-test.log');
    file_put_contents($path, "[2026-08-30 19:06:48] local.ERROR: boom\n");

    config([
        'logging.default' => 'single',
        'logging.channels.single.path' => $path,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page
            ->where('status.log.path', $path)
            ->where('status.log.lines.0', '[2026-08-30 19:06:48] local.ERROR: boom')
        );

    unlink($path);
});

test('a daily channel is tailed at today\'s file', function () {
    config([
        'logging.default' => 'daily',
        'logging.channels.daily.path' => storage_path('logs/carrot.log'),
    ]);

    $today = storage_path('logs/carrot-'.now()->toDateString().'.log');
    file_put_contents($today, "[2026-08-30 19:06:48] local.INFO: hello\n");

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page->where('status.log.path', $today));

    unlink($today);
});
