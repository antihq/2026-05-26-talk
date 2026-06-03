<?php

namespace App\Services;

use App\Models\Room;
use Pusher\Pusher;

class RoomPresence
{
    protected function pusher(): Pusher
    {
        $config = config('broadcasting.connections.reverb');

        return new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $config['options'] ?? [],
        );
    }

    public function subscribedUserIds(Room $room): array
    {
        try {
            $response = $this->pusher()->getPresenceUsers("presence-room.{$room->id}");

            return collect($response->users ?? [])
                ->pluck('id')
                ->map(fn ($id) => $id)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
