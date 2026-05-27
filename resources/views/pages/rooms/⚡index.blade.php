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

<div>
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
</div>
