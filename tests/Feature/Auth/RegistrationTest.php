<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => '山田太郎',
        'username' => 'yamada',
        'email' => 'yamada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    expect(User::firstWhere('username', 'yamada'))
        ->not->toBeNull()
        ->name->toBe('山田太郎');
});

test('usernames are stored in lowercase', function () {
    $this->post(route('register.store'), [
        'name' => '山田太郎',
        'username' => '  YaMaDa  ',
        'email' => 'yamada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::sole()->username)->toBe('yamada');
});

test('usernames must be unique', function () {
    User::factory()->create(['username' => 'yamada']);

    $this->post(route('register.store'), [
        'name' => '佐藤花子',
        'username' => 'yamada',
        'email' => 'sato@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('usernames must match the allowed format', function (string $username) {
    $this->post(route('register.store'), [
        'name' => '山田太郎',
        'username' => $username,
        'email' => 'yamada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
})->with([
    'too short' => 'abc',
    'too long' => 'abcdefghijklmnopqrstuvwxyz',
    'starts with a digit' => '1yamada',
    'contains a space' => 'yama da',
    'contains a dot' => 'yama.da',
]);
