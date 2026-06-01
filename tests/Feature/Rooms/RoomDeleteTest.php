<?php

use App\Enums\TeamRole;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('owner can delete a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->call('delete')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseMissing('rooms', [
        'id' => $room->id,
    ]);
});

test('admin can delete a room', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test('pages::rooms.edit', ['room' => $room])
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('rooms', [
        'id' => $room->id,
    ]);
});

test('member cannot delete a room', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($member)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertForbidden();

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
    ]);
});

test('user cannot delete a room from another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->assertForbidden();

    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
    ]);
});

test('deleting a room redirects to the rooms index', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.edit', ['room' => $room])
        ->call('delete')
        ->assertRedirect();
});
