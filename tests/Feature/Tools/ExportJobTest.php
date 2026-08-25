<?php

use App\Enums\ExportJobStatus;
use App\Jobs\RunExportJob;
use App\Models\AccessLog;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('exports.disk', 'exports-testing');
    config()->set('exports.definitions.daily_access_log.connection', config('database.default'));

    Storage::fake('exports-testing');
});

test('the tool catalog and the export screens require signing in', function () {
    $this->get(route('tools.index'))->assertRedirect(route('login'));
    $this->get(route('tools.exports.create'))->assertRedirect(route('login'));
    $this->get(route('tools.exports.jobs'))->assertRedirect(route('login'));
});

test('requesting an export queues a batch and issues a download code', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tools.exports.store'), [
        'definition' => 'daily_access_log',
    ]);

    $response->assertRedirect(route('tools.exports.jobs'));

    $exportJob = ExportJob::sole();

    expect($exportJob->user_id)->toBe($user->id)
        ->and($exportJob->definition)->toBe('daily_access_log')
        ->and($exportJob->status)->toBe(ExportJobStatus::Queued)
        ->and($exportJob->download_code)->toHaveLength(10);

    $response->assertSessionHas('issuedCode', $exportJob->download_code);

    Queue::assertPushed(RunExportJob::class, fn (RunExportJob $job): bool => $job->exportJob->is($exportJob));
});

test('an unknown export definition is rejected', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('tools.exports.store'), ['definition' => 'salaries'])
        ->assertSessionHasErrors('definition');

    Queue::assertNothingPushed();
});

test('running the batch writes the query result to a csv file', function () {
    AccessLog::factory()->count(3)->create(['username' => 'hanako']);

    $exportJob = ExportJob::factory()->create();

    (new RunExportJob($exportJob))->handle();

    $exportJob->refresh();

    expect($exportJob->status)->toBe(ExportJobStatus::Completed)
        ->and($exportJob->row_count)->toBe(3)
        ->and($exportJob->expires_at)->not->toBeNull();

    $csv = Storage::disk('exports-testing')->get((string) $exportJob->file_path);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('accessed_at,username,path,status_code')
        ->and($csv)->toContain('hanako');
});

test('a failing query marks the batch failed instead of throwing', function () {
    config()->set('exports.definitions.daily_access_log.sql', 'select * from nowhere');

    $exportJob = ExportJob::factory()->create();

    (new RunExportJob($exportJob))->handle();

    $exportJob->refresh();

    expect($exportJob->status)->toBe(ExportJobStatus::Failed)
        ->and($exportJob->error_message)->not->toBeNull()
        ->and($exportJob->file_path)->toBeNull();
});

test('the owner can download their own export', function () {
    $user = User::factory()->create();
    $exportJob = ExportJob::factory()->for($user)->completed()->create();

    Storage::disk('exports-testing')->put((string) $exportJob->file_path, 'id,name');

    $this->actingAs($user)
        ->get(route('tools.exports.jobs.download', $exportJob))
        ->assertOk()
        ->assertDownload($exportJob->fileName());
});

test('another user cannot download an export without its code', function () {
    $exportJob = ExportJob::factory()->completed()->create();

    Storage::disk('exports-testing')->put((string) $exportJob->file_path, 'id,name');

    $this->actingAs(User::factory()->create())
        ->get(route('tools.exports.jobs.download', $exportJob))
        ->assertForbidden();
});

test('the download code unlocks an export for another user', function () {
    $exportJob = ExportJob::factory()->completed()->create();

    Storage::disk('exports-testing')->put((string) $exportJob->file_path, 'id,name');

    $this->actingAs(User::factory()->create())
        ->post(route('tools.exports.jobs.lookup'), ['code' => $exportJob->download_code])
        ->assertRedirect(route('tools.exports.jobs'));

    $this->get(route('tools.exports.jobs.download', $exportJob))->assertOk();
});

test('an unknown download code is reported back to the visitor', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tools.exports.jobs.lookup'), ['code' => 'NOPE123456'])
        ->assertSessionHasErrors('code');
});

test('an employee id is not accepted in place of a download code', function () {
    $user = User::factory()->create(['username' => 'taro']);
    $exportJob = ExportJob::factory()->for($user)->completed()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('tools.exports.jobs.lookup'), ['code' => 'taro'])
        ->assertSessionHasErrors('code');

    $this->get(route('tools.exports.jobs.download', $exportJob))->assertForbidden();
});

test('a batch keeps listing after its definition is retired', function () {
    $user = User::factory()->create();
    ExportJob::factory()->for($user)->completed()->create(['definition' => 'retired_report']);

    $this->actingAs($user)->get(route('tools.exports.jobs'))->assertOk();
});

test('a retired definition fails the batch instead of crashing the worker', function () {
    $exportJob = ExportJob::factory()->create(['definition' => 'retired_report']);

    (new RunExportJob($exportJob))->handle();

    expect($exportJob->refresh()->status)->toBe(ExportJobStatus::Failed)
        ->and($exportJob->error_message)->toContain('retired_report');
});

test('an expired export can no longer be downloaded', function () {
    $user = User::factory()->create();
    $exportJob = ExportJob::factory()->for($user)->expired()->create();

    Storage::disk('exports-testing')->put((string) $exportJob->file_path, 'id,name');

    $this->actingAs($user)
        ->get(route('tools.exports.jobs.download', $exportJob))
        ->assertNotFound();
});
