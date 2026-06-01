<?php

use App\Enums\TeamRole;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('owner can view the edit page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertOk()
        ->assertSet('name', $room->name);
});

test('admin can view the edit page', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertOk();
});

test('member cannot view the edit page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($member)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertForbidden();
});

test('user cannot edit a room from another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertForbidden();
});

test('owner can update a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->set('name', 'New Name')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'name' => 'New Name',
    ]);
});

test('admin can update a room', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test('pages::rooms.edit', ['room' => $room])
        ->set('name', 'Updated')
        ->call('update')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'name' => 'Updated',
    ]);
});

test('member cannot update a room', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'Original']);

    Livewire::actingAs($member)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertForbidden();

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'name' => 'Original',
    ]);
});

test('room name is required when updating', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->set('name', '')
        ->call('update')
        ->assertHasErrors(['name']);
});

test('room name cannot exceed 255 characters when updating', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->set('name', str_repeat('a', 256))
        ->call('update')
        ->assertHasErrors(['name']);
});

test('updating a room redirects to the room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->set('name', 'Updated')
        ->call('update')
        ->assertRedirect();
});
