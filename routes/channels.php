<?php

use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = Room::find($roomId);

    return $room && $room->team->members()->where('user_id', $user->id)->exists()
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});
