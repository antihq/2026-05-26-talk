<?php

use App\Models\Room;
use App\Models\RoomMembership;
use App\Models\Team;
use App\Models\User;

test('guest cannot call absent endpoint', function () {
    $room = Room::factory()->create();

    $response = $this->postJson(route('presence.absent', $room));

    $response->assertUnauthorized();
});

test('absent disconnects user from room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $room);

    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertNoContent();

    $membership = RoomMembership::where('user_id', $user->id)
        ->where('room_id', $room->id)
        ->first();

    expect($membership->isConnected())->toBeFalse();
});

test('absent returns 204 no content', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $room);

    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertNoContent();
});

test('absent for room with no membership returns 204', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertNoContent();
});

test('absent for room from another team is forbidden', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertForbidden();
});

test('user cannot disconnect presence for a room they are not a team member of', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();
    $otherTeam = $victim->currentTeam;
    // Don't attach $user to this team
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    RoomMembership::present($victim, $room);

    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertForbidden();

    $membership = RoomMembership::where('user_id', $victim->id)
        ->where('room_id', $room->id)
        ->first();

    expect($membership->isConnected())->toBeTrue();
});

test('absent when already disconnected stays disconnected', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $room);

    $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    // Call absent again when already disconnected
    $response = $this
        ->actingAs($user)
        ->postJson(route('presence.absent', $room));

    $response->assertNoContent();

    $membership = RoomMembership::where('user_id', $user->id)
        ->where('room_id', $room->id)
        ->first();

    expect($membership->connected_at)->toBeNull();
});
