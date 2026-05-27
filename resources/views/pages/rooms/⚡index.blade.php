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

<div class="flex h-[calc(100vh-8rem)] -mx-4 -mb-4" x-data="{ notificationStatus: 'loading' }" x-init="
    if (window.getSubscriptionStatus) {
        window.getSubscriptionStatus().then(function(status) { notificationStatus = status; });
    }
">
    <div class="w-72 shrink-0 border-r border-zinc-200 dark:border-zinc-700 flex flex-col bg-zinc-50 dark:bg-zinc-900/50">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Rooms</h2>
        </div>

        <div class="flex-1 overflow-y-auto">
            @foreach ($this->rooms as $room)
                <a href="{{ route('rooms.show', ['current_team' => auth()->user()->currentTeam->slug, 'room' => $room]) }}" wire:navigate
                    class="block w-full text-left px-4 py-2.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors border-l-2 border-transparent">
                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100"># {{ $room->name }}</div>
                    @if ($room->description)
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ $room->description }}</div>
                    @endif
                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">{{ $room->messages_count }} messages</div>
                </a>
            @endforeach
        </div>

        @can('create', App\Models\Room::class)
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                <a href="{{ route('rooms.create', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate
                    class="w-full flex items-center gap-2 rounded border border-dashed border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs font-medium text-zinc-500 dark:text-zinc-400 hover:border-zinc-400 dark:hover:border-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                    + New room
                </a>
            </div>
        @endcan

        <div class="p-3 border-t border-zinc-200 dark:border-zinc-700">
            <template x-if="notificationStatus === 'loading'">
                <span class="text-xs text-zinc-400">Notifications...</span>
            </template>
            <template x-if="notificationStatus === 'subscribed'">
                <span class="text-xs text-green-600 dark:text-green-400">Notifications on</span>
            </template>
            <template x-if="notificationStatus === 'denied'">
                <span class="text-xs text-zinc-400">Notifications blocked</span>
            </template>
            <template x-if="notificationStatus === 'unsupported'">
                <span class="text-xs text-zinc-400">Push not supported</span>
            </template>
            <template x-if="notificationStatus === 'unsubscribed'">
                <button @click="
                    if (window.subscribeToPush) {
                        window.subscribeToPush().then(function(success) {
                            if (success) { notificationStatus = 'subscribed'; } else { notificationStatus = 'unsubscribed'; }
                        });
                    }
                " class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                    Enable notifications
                </button>
            </template>
        </div>
    </div>

    <div class="flex-1 flex items-center justify-center">
        <div class="text-center">
            <h2 class="text-lg font-semibold text-zinc-700 dark:text-zinc-300">Welcome to Campfire</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Select a room to start chatting</p>
        </div>
    </div>
</div>
