<?php

use App\Models\User;

test('the studio requires signing in', function () {
    $this->get(route('tools.studio'))->assertRedirect(route('login'));
});

test('the studio shows the first allowlisted page by default', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tools.studio'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tools/studio')
            ->where('current.key', 'example')
            ->where('current.url', 'https://example.com/')
        );
});

test('a page outside the allowlist is not framed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tools.studio', ['page' => 'https://evil.example']))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.studio', ['page' => 'unknown']))
        ->assertNotFound();
});

test('every allowlisted page is an external https url', function () {
    /** @var array<string, array{url: string}> $pages */
    $pages = config('tools.studio');

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $key => $page) {
        expect($page['url'])->toStartWith('https://', "[{$key}] must be https");
        expect(parse_url($page['url'], PHP_URL_HOST))
            ->not->toBe(parse_url(config('app.url'), PHP_URL_HOST), "[{$key}] must not frame our own origin");
    }
});
