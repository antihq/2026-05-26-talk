<?php

use App\Events\UnreadRoomUpdated;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

test('broadcasts on private user channel', function () {
    $user = User::factory()->create();
    $room = Room::factory()->create(['team_id' => $user->currentTeam->id]);

    $event = new UnreadRoomUpdated($room, $user);

    $channel = $event->broadcastOn();
    expect($channel)->toBeInstanceOf(PrivateChannel::class);
    expect($channel->name)->toBe('private-user.'.$user->id);
});

test('broadcast payload contains room id and unread count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $event = new UnreadRoomUpdated($room, $user);
    $payload = $event->broadcastWith();

    expect($payload)->toMatchArray([
        'room_id' => $room->id,
        'unread_count' => 1,
    ]);
});

test('unread count reflects multiple unread rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $roomA->id, 'user_id' => $user->id]);
    Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $user->id]);

    $event = new UnreadRoomUpdated($roomA, $user);
    $payload = $event->broadcastWith();

    expect($payload['unread_count'])->toBe(2);
});

test('unread count excludes read rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $roomA->id, 'user_id' => $user->id]);
    Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $user->id]);
    \App\Models\RoomMembership::create([
        'user_id' => $user->id,
        'room_id' => $roomA->id,
        'last_read_at' => now(),
    ]);

    $event = new UnreadRoomUpdated($roomB, $user);
    $payload = $event->broadcastWith();

    expect($payload['unread_count'])->toBe(1);
});
