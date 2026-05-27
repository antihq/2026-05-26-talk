<?php

use App\Enums\TeamRole;
use App\Models\Room;
use App\Models\User;
use Livewire\Livewire;

test('owner can create a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('pages::rooms.create')
        ->set('name', 'General')
        ->set('description', 'General discussion')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'team_id' => $team->id,
        'name' => 'General',
        'description' => 'General discussion',
        'created_by' => $user->id,
    ]);
});

test('admin can create a room', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    Livewire::actingAs($admin)
        ->test('pages::rooms.create')
        ->set('name', 'Admin Room')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'team_id' => $team->id,
        'name' => 'Admin Room',
    ]);
});

test('member cannot create a room', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    Livewire::actingAs($member)
        ->test('pages::rooms.create')
        ->set('name', 'Member Room')
        ->call('create')
        ->assertForbidden();

    $this->assertDatabaseMissing('rooms', [
        'team_id' => $team->id,
        'name' => 'Member Room',
    ]);
});

test('room name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.create')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name']);
});

test('room name cannot exceed 255 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.create')
        ->set('name', str_repeat('a', 256))
        ->call('create')
        ->assertHasErrors(['name']);
});

test('room description cannot exceed 1000 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.create')
        ->set('name', 'General')
        ->set('description', str_repeat('a', 1001))
        ->call('create')
        ->assertHasErrors(['description']);
});

test('creating a room redirects to the new room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('pages::rooms.create')
        ->set('name', 'General')
        ->call('create')
        ->assertRedirect();
});
