<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;

test('broadcasts on presence room channel', function () {
    $user = User::factory()->create();
    $room = Room::factory()->create(['team_id' => $user->currentTeam->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Hello!',
    ]);

    $event = new MessageSent($message);

    $channel = $event->broadcastOn();
    expect($channel)->toBeInstanceOf(PresenceChannel::class);
    expect($channel->name)->toBe('presence-room.'.$room->id);
});

test('broadcast payload contains message data', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $room = Room::factory()->create(['team_id' => $user->currentTeam->id]);
    $message = Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Hello!',
    ]);

    $event = new MessageSent($message);
    $payload = $event->broadcastWith();

    expect($payload)->toMatchArray([
        'id' => $message->id,
        'room_id' => $room->id,
        'user_id' => $user->id,
        'user_name' => 'Alice',
        'body' => 'Hello!',
    ]);
    expect($payload)->toHaveKey('created_at');
});
