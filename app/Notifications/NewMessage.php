<?php

namespace App\Notifications;

use App\Models\Room;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\DeclarativeWebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Room $room,
        public User $sender,
        public string $body,
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): DeclarativeWebPushMessage
    {
        $team = $notifiable->currentTeam;

        $url = route('dashboard', [
            'current_team' => $team?->slug ?? $this->room->team->slug,
        ]).'?room='.$this->room->id;

        return (new DeclarativeWebPushMessage)
            ->title('#'.$this->room->name)
            ->body($this->sender->name.': '.$this->body)
            ->icon('/favicon.ico')
            ->action('Open room', 'open_room', $url)
            ->navigate($url);
    }
}
