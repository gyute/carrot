<?php

use App\Enums\ToolRequestStatus;
use App\Events\ToolRequestSubmitted;
use App\Models\Message;
use App\Models\ToolRequest;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function requestPayload(array $overrides = []): array
{
    return [
        'title' => '請求書の消費税をまとめて計算したい',
        'body' => '今は電卓で一件ずつ計算していて、件数が多い月は半日かかります。',
        'categories' => ['データ'],
        ...$overrides,
    ];
}

test('filing a request stamps the requester department and tells the development team', function () {
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create(['department' => '営業']);

    $this->actingAs($requester)
        ->post(route('tools.requests.store'), requestPayload())
        ->assertRedirect();

    $toolRequest = ToolRequest::query()->sole();

    expect($toolRequest->status)->toBe(ToolRequestStatus::Open)
        ->and($toolRequest->department)->toBe('営業')
        ->and($toolRequest->categories)->toBe(['データ']);

    expect(Message::query()->where('recipient_id', $admin->id)->exists())->toBeTrue();
});

test('the department is stamped from the requester, not taken from the form', function () {
    User::factory()->admin()->create();

    $this->actingAs(User::factory()->create(['department' => '営業']))
        ->post(route('tools.requests.store'), requestPayload(['department' => '経理']))
        ->assertRedirect();

    expect(ToolRequest::query()->sole()->department)->toBe('営業');
});

test('a request is visible to its own department and to admins, and to nobody else', function () {
    $toolRequest = ToolRequest::factory()->create(['department' => '営業']);

    $colleague = User::factory()->create(['department' => '営業']);
    $outsider = User::factory()->create(['department' => '経理']);

    $this->actingAs($colleague)->get(route('tools.requests.show', $toolRequest))->assertOk();
    $this->actingAs($outsider)->get(route('tools.requests.show', $toolRequest))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tools.requests.show', $toolRequest))->assertOk();

    $this->actingAs($outsider)
        ->get(route('tools.requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests', 0));

    $this->actingAs($colleague)
        ->get(route('tools.requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests', 1));
});

test('a request with no department stays with its requester', function () {
    $toolRequest = ToolRequest::factory()->create(['department' => null]);

    $this->actingAs(User::factory()->create(['department' => null]))
        ->get(route('tools.requests.show', $toolRequest))
        ->assertForbidden();

    $this->actingAs($toolRequest->user)
        ->get(route('tools.requests.show', $toolRequest))
        ->assertOk();
});

test('only the requester edits a request, and only while it is open', function () {
    $toolRequest = ToolRequest::factory()->create(['department' => '営業']);
    $colleague = User::factory()->create(['department' => '営業']);

    $this->actingAs($colleague)
        ->patch(route('tools.requests.update', $toolRequest), requestPayload())
        ->assertForbidden();

    $this->actingAs($toolRequest->user)
        ->patch(route('tools.requests.update', $toolRequest), requestPayload(['title' => '書き直した']))
        ->assertRedirect();

    expect($toolRequest->refresh()->title)->toBe('書き直した');

    $toolRequest->forceFill(['status' => ToolRequestStatus::Accepted])->save();

    $this->actingAs($toolRequest->user)
        ->patch(route('tools.requests.update', $toolRequest), requestPayload(['title' => 'もう一度']))
        ->assertForbidden();
});

test('the requester withdraws their own request while it is live', function () {
    $toolRequest = ToolRequest::factory()->create();

    $this->actingAs($toolRequest->user)
        ->delete(route('tools.requests.destroy', $toolRequest))
        ->assertRedirect();

    expect($toolRequest->refresh()->status)->toBe(ToolRequestStatus::Withdrawn);

    $this->actingAs($toolRequest->user)
        ->delete(route('tools.requests.destroy', $toolRequest))
        ->assertForbidden();
});

test('submitting dispatches the event the notifications hang off', function () {
    Event::fake([ToolRequestSubmitted::class]);

    $this->actingAs(User::factory()->create())
        ->post(route('tools.requests.store'), requestPayload())
        ->assertRedirect();

    Event::assertDispatched(ToolRequestSubmitted::class);
});
