<?php

namespace App\Events;

use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Queue\SerializesModels;

class UnreadRoomUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use SerializesModels;

    public function __construct(
        public Room $room,
        public User $user,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->user->id);
    }

    public function broadcastWith(): array
    {
        $teamId = $this->user->currentTeam?->id ?? $this->room->team_id;

        $unreadCount = Room::forTeam($teamId)
            ->unreadFor($this->user)
            ->count();

        return [
            'room_id' => $this->room->id,
            'unread_count' => $unreadCount,
        ];
    }
}
