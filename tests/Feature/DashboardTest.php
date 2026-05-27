<?php

use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('dashboard redirects authenticated users to rooms', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('rooms.index'));
});

test('rooms index page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertOk();
});
