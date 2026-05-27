<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Rooms')] class extends Component
{
    public function getRoomsProperty()
    {
        return auth()->user()->currentTeam->rooms()
            ->withCount('messages')
            ->orderBy('name')
            ->get();
    }
}; ?>

<div x-data="{ notificationStatus: 'loading' }" x-init="
    if (window.getSubscriptionStatus) {
        window.getSubscriptionStatus().then(function(status) { notificationStatus = status; });
    }
">
    <h1>Rooms</h1>

    <ul>
        @foreach ($this->rooms as $room)
            <li>
                <a href="{{ route('rooms.show', ['current_team' => auth()->user()->currentTeam->slug, 'room' => $room]) }}" wire:navigate>
                    # {{ $room->name }}
                </a>
            </li>
        @endforeach
    </ul>

    @can('create', App\Models\Room::class)
        <p><a href="{{ route('rooms.create', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate>+ New room</a></p>
    @endcan

    <template x-if="notificationStatus === 'unsubscribed'">
        <button @click="
            if (window.subscribeToPush) {
                window.subscribeToPush().then(function(success) {
                    if (success) { notificationStatus = 'subscribed'; } else { notificationStatus = 'unsubscribed'; }
                });
            }
        ">Enable notifications</button>
    </template>
    <template x-if="notificationStatus === 'denied'">
        <p>Notifications blocked</p>
    </template>
    <template x-if="notificationStatus === 'unsupported'">
        <p>Push not supported</p>
    </template>
    <template x-if="notificationStatus === 'subscribed'">
        <p>Notifications on</p>
    </template>
</div>
