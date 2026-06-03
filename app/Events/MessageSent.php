<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow, ShouldRescue
{
    use SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.'.$this->message->room_id);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
