<?php

use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\ToolSubmission;
use App\Models\User;

test('by default both halves of the tool module are on', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tools.submissions.create'))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.requests.create'))
        ->assertOk();
});

test('submissions `none` takes the submission and approval screens away entirely', function () {
    $tool = Tool::factory()->create();
    $submission = ToolSubmission::factory()->create();
    config(['catalog.features.submissions' => 'none']);

    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($member)->get(route('tools.submissions.index'))->assertNotFound();
    $this->actingAs($member)->get(route('tools.submissions.show', $submission))->assertNotFound();
    $this->actingAs($member)->get(route('tools.change.create', $tool))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.approvals.index'))->assertNotFound();

    // The catalog itself is untouched, and says the feature is gone.
    $this->actingAs($member)
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('features.submissions', false)
            ->where('features.maySubmit', false)
            ->where('pendingApprovals', 0));
});

test('submissions `admin` leaves the screens up but only the development team files', function () {
    $owner = User::factory()->create();
    $tool = Tool::factory()->create(['owner_id' => $owner->id]);
    $submission = ToolSubmission::factory()->create(['user_id' => $owner->id]);
    config(['catalog.features.submissions' => 'admin']);

    // A member keeps their history but cannot open anything new.
    $this->actingAs($owner)->get(route('tools.submissions.index'))->assertOk();
    $this->actingAs($owner)->get(route('tools.submissions.show', $submission))->assertOk();
    $this->actingAs($owner)->get(route('tools.submissions.create'))->assertForbidden();
    $this->actingAs($owner)->get(route('tools.change.create', $tool))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tools.submissions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('features.submissions', true)
            ->where('features.maySubmit', true));
});

test('the catalog only offers to register a tool when this person may', function () {
    Tool::factory()->create();
    config(['catalog.features.submissions' => 'admin']);

    // The button is a link to a screen the policy refuses, so the catalog has
    // to read the same flag the submission screens do.
    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.maySubmit', false));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.maySubmit', true));
});

test('requests off takes the request screens away', function () {
    $toolRequest = ToolRequest::factory()->create();
    config(['catalog.features.requests' => false]);

    $member = User::factory()->create();

    $this->actingAs($member)->get(route('tools.requests.index'))->assertNotFound();
    $this->actingAs($member)->get(route('tools.requests.show', $toolRequest))->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.requests.index'))
        ->assertNotFound();

    $this->actingAs($member)
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.requests', false)->where('openRequests', 0));
});

test('an unknown submission mode falls back to `all` rather than locking everyone out', function () {
    config(['catalog.features.submissions' => 'nonsense']);

    $this->actingAs(User::factory()->create())
        ->get(route('tools.submissions.create'))
        ->assertOk();
});
