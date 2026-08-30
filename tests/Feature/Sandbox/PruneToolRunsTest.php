<?php

use App\Enums\ToolRunStatus;
use App\Models\ToolRun;

test('old finished runs are pruned but queued ones are kept', function () {
    config(['sandbox.run_retention_days' => 30, 'sandbox.workdir' => sys_get_temp_dir().'/carrot-prune-test']);

    ToolRun::factory()->completed()->create(['created_at' => now()->subDays(40)]);
    ToolRun::factory()->create(['status' => ToolRunStatus::Queued, 'created_at' => now()->subDays(40)]);
    ToolRun::factory()->completed()->create(['created_at' => now()->subDays(5)]);

    $this->artisan('carrot:prune-runs')
        ->expectsOutputToContain('Deleted 1 runs')
        ->assertSuccessful();

    expect(ToolRun::query()->count())->toBe(2);
});
