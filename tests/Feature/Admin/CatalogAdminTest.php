<?php

use App\Enums\ToolRunStatus;
use App\Enums\ToolStatus;
use App\Models\Tag;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;

test('the catalog admin screens are for admins only', function () {
    $manager = User::factory()->manager('開発')->create();

    foreach (['admin.tools.index', 'admin.tags.index', 'admin.runs.index'] as $route) {
        $this->actingAs($manager)->get(route($route))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
    }
});

test('the tool list shows deleted rows and brings them back', function () {
    $admin = User::factory()->admin()->create();
    $running = Tool::factory()->create(['name' => 'Alive']);
    $gone = Tool::factory()->create(['name' => 'Gone']);
    $gone->delete();

    $this->actingAs($admin)->get(route('admin.tools.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/tools/index')
            ->has('tools', 2)
            ->where('counts.deleted', 1)
        );

    $this->actingAs($admin)->get(route('admin.tools.index', ['state' => 'deleted']))
        ->assertInertia(fn ($page) => $page
            ->has('tools', 1)
            ->where('tools.0.name', 'Gone')
            ->where('tools.0.status', 'deleted')
        );

    $this->actingAs($admin)->post(route('admin.tools.untrash', $gone->ulid))->assertRedirect();

    expect(Tool::query()->whereKey($gone->id)->exists())->toBeTrue()
        ->and($running->fresh()?->status)->toBe(ToolStatus::Running);
});

test('purging a deleted tool removes the row for good', function () {
    $admin = User::factory()->admin()->create();
    $tool = Tool::factory()->create();
    $tool->delete();

    $this->actingAs($admin)->delete(route('admin.tools.purge', $tool->ulid))->assertRedirect();

    expect(Tool::withTrashed()->count())->toBe(0);
});

test('a running tool is not in the trash and cannot be purged', function () {
    $tool = Tool::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.tools.purge', $tool->ulid))
        ->assertNotFound();

    expect(Tool::query()->count())->toBe(1);
});

test('renaming a tag onto an existing one merges them', function () {
    $admin = User::factory()->admin()->create();
    $keep = Tag::factory()->create(['value' => 'データ']);
    $typo = Tag::factory()->create(['value' => 'でーた']);

    $a = Tool::factory()->create();
    $b = Tool::factory()->create();
    $a->tags()->attach($keep);
    $b->tags()->attach($typo);

    $this->actingAs($admin)->get(route('admin.tags.index'))
        ->assertInertia(fn ($page) => $page->component('admin/tags/index')->has('tags', 2));

    $this->actingAs($admin)
        ->patch(route('admin.tags.update', $typo), ['value' => 'データ'])
        ->assertRedirect();

    expect(Tag::query()->count())->toBe(1)
        ->and($b->fresh()?->categories())->toBe(['データ'])
        ->and($a->fresh()?->categories())->toBe(['データ']);
});

test('renaming a tag to a free name keeps it, and deleting detaches it', function () {
    $admin = User::factory()->admin()->create();
    $tag = Tag::factory()->create(['value' => 'でーた']);
    $tool = Tool::factory()->create();
    $tool->tags()->attach($tag);

    $this->actingAs($admin)->patch(route('admin.tags.update', $tag), ['value' => 'データ'])->assertRedirect();

    expect($tag->fresh()?->value)->toBe('データ');

    $this->actingAs($admin)->delete(route('admin.tags.destroy', $tag))->assertRedirect();

    expect(Tag::query()->count())->toBe(0)
        ->and($tool->fresh()?->categories())->toBe([]);
});

test('the run list filters by status, deletes one and prunes old ones', function () {
    $admin = User::factory()->admin()->create();
    $tool = Tool::factory()->script()->create();

    $recent = ToolRun::factory()->completed()->create(['tool_id' => $tool->id]);
    $queued = ToolRun::factory()->create(['tool_id' => $tool->id, 'status' => ToolRunStatus::Queued]);
    $old = ToolRun::factory()->completed()->create(['tool_id' => $tool->id]);
    $old->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->actingAs($admin)->get(route('admin.runs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/runs/index')
            ->where('runs.total', 3)
            ->where('runs.data.0.tool.name', $tool->name)
        );

    $this->actingAs($admin)->get(route('admin.runs.index', ['status' => 'queued']))
        ->assertInertia(fn ($page) => $page->where('runs.total', 1));

    $this->actingAs($admin)->delete(route('admin.runs.destroy', $recent))->assertRedirect();

    expect(ToolRun::query()->count())->toBe(2);

    $this->actingAs($admin)->post(route('admin.runs.prune'))->assertRedirect();

    // The queued one is never swept, however old; the finished one goes.
    expect(ToolRun::query()->pluck('id')->all())->toBe([$queued->id]);
});
