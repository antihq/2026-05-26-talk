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
            ->withMax('messages', 'created_at')
            ->with(['roomReads' => fn ($q) => $q->where('user_id', auth()->id())])
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex flex-wrap items-center gap-x-3" wire:poll.5s>
    <flux:heading level="1" class="lowercase">Rooms</flux:heading>

    <nav class="flex flex-wrap gap-x-3">
        @foreach ($this->rooms as $room)
            <div class="flex items-center gap-x-1.5">
                @if ($room->isUnreadFor(auth()->user()))
                    <span class="inline-block w-2 h-2 rounded-full bg-lime-500 shrink-0" aria-label="unread"></span>
                @endif
                <flux:link href="{{ route('rooms.show', ['current_team' => auth()->user()->currentTeam->slug, 'room' => $room]) }}" wire:navigate>
                    # {{ $room->name }}
                </flux:link>
            </div>
        @endforeach

        @can('create', App\Models\Room::class)
            <div>
                <flux:link href="{{ route('rooms.create', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate>+ New room</flux:link>
            </div>
        @endcan
    </nav>
</div>
