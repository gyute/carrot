<?php

use App\Enums\ExportJobStatus;
use App\Jobs\RunExportJob;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('exports.disk', 'exports-testing');
    config()->set('exports.definitions.users.connection', config('database.default'));

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
        'definition' => 'users',
    ]);

    $response->assertRedirect(route('tools.exports.jobs'));

    $exportJob = ExportJob::sole();

    expect($exportJob->user_id)->toBe($user->id)
        ->and($exportJob->definition)->toBe('users')
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
    $user = User::factory()->create(['username' => 'hanako', 'name' => '山田 花子']);

    $exportJob = ExportJob::factory()->for($user)->create();

    (new RunExportJob($exportJob))->handle();

    $exportJob->refresh();

    expect($exportJob->status)->toBe(ExportJobStatus::Completed)
        ->and($exportJob->row_count)->toBe(1)
        ->and($exportJob->expires_at)->not->toBeNull();

    $csv = Storage::disk('exports-testing')->get((string) $exportJob->file_path);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('id,username,name,email')
        ->and($csv)->toContain('hanako');
});

test('a failing query marks the batch failed instead of throwing', function () {
    config()->set('exports.definitions.users.sql', 'select * from nowhere');

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
        ->post(route('tools.exports.jobs.lookup'), ['key' => $exportJob->download_code])
        ->assertRedirect(route('tools.exports.jobs'));

    $this->get(route('tools.exports.jobs.download', $exportJob))->assertOk();
});

test('an unknown lookup key is reported back to the visitor', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tools.exports.jobs.lookup'), ['key' => 'NOPE123456'])
        ->assertSessionHasErrors('key');
});

test('an expired export can no longer be downloaded', function () {
    $user = User::factory()->create();
    $exportJob = ExportJob::factory()->for($user)->expired()->create();

    Storage::disk('exports-testing')->put((string) $exportJob->file_path, 'id,name');

    $this->actingAs($user)
        ->get(route('tools.exports.jobs.download', $exportJob))
        ->assertNotFound();
});
