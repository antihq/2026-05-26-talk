<?php

use App\Enums\TeamRole;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomMembership;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

// isUnreadFor

test('room with no messages is not unread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $user->id)])
        ->find($room->id);

    expect($room->isUnreadFor($user))->toBeFalse();
});

test('room with messages and no read record is unread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $user->id)])
        ->find($room->id);

    expect($room->isUnreadFor($user))->toBeTrue();
});

test('room with messages and null last_read_at is unread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);
    RoomMembership::create(['user_id' => $user->id, 'room_id' => $room->id, 'last_read_at' => null]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $user->id)])
        ->find($room->id);

    expect($room->isUnreadFor($user))->toBeTrue();
});

test('room with messages newer than last_read_at is unread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'created_at' => now(),
    ]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => $message->created_at->clone()->subMinute(),
    ]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $user->id)])
        ->find($room->id);

    expect($room->isUnreadFor($user))->toBeTrue();
});

test('room with messages older than last_read_at is not unread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'created_at' => now()->subHour(),
    ]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => $message->created_at->clone()->addMinute(),
    ]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $user->id)])
        ->find($room->id);

    expect($room->isUnreadFor($user))->toBeFalse();
});

// scopeUnreadFor

test('unread scope counts rooms with messages but no read record', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(1);
});

test('unread scope counts rooms with stale read record', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
    ]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => $message->created_at->clone()->subSecond(),
    ]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(1);
});

test('unread scope excludes rooms with current read record', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
    ]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => $message->created_at->clone()->addSecond(),
    ]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(0);
});

test('unread scope excludes rooms with no messages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Room::factory()->create(['team_id' => $team->id]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(0);
});

test('unread scope does not count rooms from other teams', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherTeam = Team::factory()->create();
    $otherRoom = Room::factory()->create(['team_id' => $otherTeam->id]);
    Message::factory()->create(['room_id' => $otherRoom->id, 'user_id' => $user->id]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(0);
});

// scopeForTeam

test('for team scope filters by team model', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Room::factory()->create(['team_id' => Team::factory()->create()->id]);

    $rooms = Room::forTeam($team)->get();

    expect($rooms)->toHaveCount(1);
    expect($rooms->first()->id)->toBe($room->id);
});

test('for team scope filters by team id', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Room::factory()->create(['team_id' => Team::factory()->create()->id]);

    $rooms = Room::forTeam($team->id)->get();

    expect($rooms)->toHaveCount(1);
    expect($rooms->first()->id)->toBe($room->id);
});

// RoomMembership upsert on viewing a room

test('viewing a room creates a room membership record', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    $this->assertDatabaseHas('room_memberships', [
        'user_id' => $user->id,
        'room_id' => $room->id,
    ]);
});

test('viewing a room updates existing room membership record', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    expect(RoomMembership::where('user_id', $user->id)->where('room_id', $room->id)->count())->toBe(1);
});

test('viewing a room sets last_read_at', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    $read = RoomMembership::where('user_id', $user->id)->where('room_id', $room->id)->first();
    expect($read->last_read_at)->not->toBeNull();
    expect($read->last_read_at->diffInSeconds(now()))->toBeLessThan(5);
});

// Unread dot in room index

test('index shows unread dot for unread room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'General']);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertSeeHtml('bg-lime-500');
});

test('index hides unread dot for read room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'General']);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);
    RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertDontSeeHtml('bg-lime-500');
});

test('index hides unread dot when room has no messages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Room::factory()->create(['team_id' => $team->id, 'name' => 'Empty']);

    Livewire::actingAs($user)
        ->test('pages::rooms.index')
        ->assertDontSeeHtml('bg-lime-500');
});

// Cross-user isolation

test('isUnreadFor does not mix read state between users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $team = $userA->currentTeam;
    $team->members()->attach($userB, ['role' => TeamRole::Member->value]);
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $userA->id]);

    RoomMembership::create([
        'user_id' => $userA->id,
        'room_id' => $room->id,
        'last_read_at' => now(),
    ]);

    $room = $team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $userA->id)])
        ->find($room->id);

    expect($room->isUnreadFor($userA))->toBeFalse();
    expect($team->rooms()
        ->withCount('messages')
        ->withMax('messages', 'created_at')
        ->with(['roomMemberships' => fn ($q) => $q->where('user_id', $userB->id)])
        ->find($room->id)
        ->isUnreadFor($userB)
    )->toBeTrue();
});

// scopeUnreadFor with multiple rooms

test('unread scope counts multiple unread rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);
    Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $roomA->id, 'user_id' => $user->id]);
    Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $user->id]);

    $count = Room::forTeam($team)->unreadFor($user)->count();

    expect($count)->toBe(2);
});

// markAsRead on mount

test('mount marks room as read', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    $read = RoomMembership::where('user_id', $user->id)->where('room_id', $room->id)->first();
    expect($read->last_read_at)->not->toBeNull();
});

// RoomMembership Connectable

test('present creates a membership record with connected_at', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $membership = RoomMembership::present($user, $room);

    expect($membership->connected_at)->not->toBeNull();
    expect($membership->last_read_at)->not->toBeNull();
});

test('present refreshes connected_at if already connected', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $first = RoomMembership::present($user, $room);
    $original = $first->fresh()->connected_at;

    $this->travel(5)->seconds();

    RoomMembership::present($user, $room);

    $membership = RoomMembership::where('user_id', $user->id)->where('room_id', $room->id)->first();
    expect($membership->connected_at->gt($original))->toBeTrue();
});

test('present reconnects if connection is stale', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $membership = RoomMembership::present($user, $room);
    $this->travel(RoomMembership::CONNECTION_TTL + 1)->seconds();

    RoomMembership::present($user, $room);

    $membership->refresh();
    expect($membership->isConnected())->toBeTrue();
});

test('present disconnects user from other rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $roomA);
    RoomMembership::present($user, $roomB);

    $roomAconnection = RoomMembership::where('user_id', $user->id)->where('room_id', $roomA->id)->first();
    expect($roomAconnection->isConnected())->toBeFalse();
});

test('connected scope includes only memberships within TTL', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $roomA);

    $this->travel(RoomMembership::CONNECTION_TTL + 1)->seconds();

    RoomMembership::present($user, $roomB);

    $connected = RoomMembership::connected()->get();
    expect($connected)->toHaveCount(1);
    expect($connected->first()->room_id)->toBe($roomB->id);
});

test('disconnected scope includes stale memberships', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($user, $room);

    $this->travel(RoomMembership::CONNECTION_TTL + 1)->seconds();

    $disconnected = RoomMembership::disconnected()->get();
    expect($disconnected->pluck('room_id'))->toContain($room->id);
});

test('markDisconnected sets connected_at to null', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $membership = RoomMembership::present($user, $room);
    $membership->markDisconnected();

    expect($membership->fresh()->connected_at)->toBeNull();
});

test('markDisconnected when already disconnected stays null', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $membership = RoomMembership::present($user, $room);
    $membership->markDisconnected();

    expect($membership->fresh()->connected_at)->toBeNull();

    $membership->markDisconnected();

    expect($membership->fresh()->connected_at)->toBeNull();
});

test('refreshConnection refreshes connected_at', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    $membership = RoomMembership::present($user, $room);

    $this->travel(30)->seconds();

    $membership->refreshConnection();

    expect($membership->fresh()->isConnected())->toBeTrue();
    expect($membership->fresh()->connected_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('disconnectAll disconnects all memberships', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $team = $userA->currentTeam;
    $team->members()->attach($userB, ['role' => TeamRole::Member->value]);
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);

    RoomMembership::present($userA, $roomA);
    RoomMembership::present($userA, $roomB);
    RoomMembership::present($userB, $roomA);

    RoomMembership::disconnectAll();

    expect(RoomMembership::connected()->count())->toBe(0);
});
