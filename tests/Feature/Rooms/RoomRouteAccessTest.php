<?php

use App\Models\Team;
use App\Models\User;

test('guests are redirected to login when accessing rooms index', function () {
    $user = User::factory()->create();

    $this->get(route('rooms.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login when accessing rooms create', function () {
    $user = User::factory()->create();

    $this->get(route('rooms.create'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login when accessing a room', function () {
    $user = User::factory()->create();
    $room = $user->currentTeam->rooms()->create(['name' => 'Test', 'created_by' => $user->id]);

    $this->get(route('rooms.show', ['room' => $room]))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit rooms index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('rooms.index'))
        ->assertOk();
});

test('authenticated users can visit rooms create page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('rooms.create'))
        ->assertOk();
});

test('authenticated users can view a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = $team->rooms()->create(['name' => 'General', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('rooms.show', ['room' => $room]))
        ->assertOk();
});

test('user gets 403 when accessing a room from another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = $otherTeam->rooms()->create(['name' => 'Secret', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('rooms.show', ['room' => $room]))
        ->assertForbidden();
});

test('user gets 403 when on another teams route prefix', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = $otherTeam->rooms()->create(['name' => 'Secret', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('rooms.show', ['current_team' => $otherTeam->slug, 'room' => $room]))
        ->assertForbidden();
});

test('rooms index does not include rooms from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->rooms()->create(['name' => 'Invisible Room', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('rooms.index'))
        ->assertDontSee('Invisible Room');
});
