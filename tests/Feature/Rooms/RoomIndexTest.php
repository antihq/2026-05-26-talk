<?php

use App\Enums\TeamRole;
use App\Models\Message;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('rooms are ordered by name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $roomB = Room::factory()->create(['team_id' => $team->id, 'name' => 'Beta']);
    $roomA = Room::factory()->create(['team_id' => $team->id, 'name' => 'Alpha']);
    $roomC = Room::factory()->create(['team_id' => $team->id, 'name' => 'Gamma']);

    $names = Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->get('rooms')
        ->pluck('name')
        ->values()
        ->toArray();

    expect($names)->toBe(['Alpha', 'Beta', 'Gamma']);
});

test('rooms include message count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->count(3)->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $rooms = Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->get('rooms');

    $found = $rooms->firstWhere('id', $room->id);
    expect($found->messages_count)->toBe(3);
});

test('index shows room names', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'General']);

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertSee('General');
});

test('index only shows rooms for the current team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    Room::factory()->create(['team_id' => $otherTeam->id, 'name' => 'Other Team Room']);

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertDontSee('Other Team Room');
});

test('index shows new room link for owners', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertSee('+ New room');
});

test('index hides new room link for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    Livewire::actingAs($member)
        ->test('pages::rooms.index')
        ->assertDontSee('+ New room');
});

test('index shows welcome message when no rooms exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertSee('Select a room to start chatting');
});
