<?php

use App\Models\User;

test('the portal home page returns a successful response', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertOk();
});

test('guests are sent to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
