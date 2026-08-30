<?php

use App\Models\Tag;
use App\Models\Tool;
use App\Models\User;

test('the catalog requires signing in', function () {
    $this->get(route('tools.index'))->assertRedirect(route('login'));
});

test('the catalog renders for a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tools/index'));
});

test('the catalog lists tools from the database with their tags', function () {
    $tool = Tool::factory()->link('/tools/example')->create([
        'name' => 'Example',
        'department' => '開発',
    ]);
    $tool->tags()->attach(Tag::factory()->create(['value' => 'データ']));

    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tools/index')
            ->has('tools', 1)
            ->where('tools.0.name', 'Example')
            ->where('tools.0.href', '/tools/example')
            ->where('tools.0.status', 'running')
            ->where('tools.0.tags.category', ['データ'])
            ->where('tools.0.tags.department', ['開発'])
        );
});

test('tag groups carry counts, with status first', function () {
    $data = Tag::factory()->create(['value' => 'データ']);

    Tool::factory()->count(2)->create(['department' => '開発'])
        ->each(fn (Tool $tool) => $tool->tags()->attach($data));
    Tool::factory()->deprecated()->create(['department' => '総務']);

    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tagGroups.0.key', 'status')
            ->where('tagGroups.0.options', [
                ['value' => 'running', 'count' => 2],
                ['value' => 'deprecated', 'count' => 1],
            ])
            ->where('tagGroups.1.key', 'category')
            ->where('tagGroups.1.options', [['value' => 'データ', 'count' => 2]])
            ->where('tagGroups.2.key', 'department')
            ->where('tagGroups.2.options', [
                ['value' => '総務', 'count' => 1],
                ['value' => '開発', 'count' => 2],
            ])
        );
});

test('embed and deprecated tools lead to their own page', function () {
    $embed = Tool::factory()->embed()->create(['slug' => 'docs', 'name' => 'Docs']);
    $old = Tool::factory()->deprecated()->create(['name' => 'Old']);

    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tools.0.href', "/tools/{$embed->ulid}")
            ->where('tools.1.href', "/tools/{$old->ulid}")
            ->where('tools.1.status', 'deprecated')
        );
});

test('an embed tool is framed on its own page', function () {
    $tool = Tool::factory()->embed('https://docs.example/')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.show', $tool))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tools/show')
            ->where('tool.embedUrl', 'https://docs.example/')
        );
});

test('a tool on our own origin is never framed', function () {
    $tool = Tool::factory()->embed(config('app.url').'/tools')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.show', $tool))
        ->assertInertia(fn ($page) => $page->where('tool.embedUrl', null));
});
