<?php

use App\Models\Room;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Create Room')] class extends Component
{
    public string $name = '';

    public function create(): void
    {
        $this->authorize('create', Room::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $room = Room::create([
            'team_id' => auth()->user()->currentTeam->id,
            'name' => $this->name,
            'created_by' => auth()->id(),
        ]);

        $this->redirectRoute('rooms.show', [
            'current_team' => auth()->user()->currentTeam->slug,
            'room' => $room,
        ], navigate: true);
    }
}; ?>

<div>
    <h1>Create a new room</h1>

    <form wire:submit="create">
        <flux:field>
            <flux:label>Room name</flux:label>
            <flux:input wire:model="name" type="text" required autofocus />
            <flux:error name="name" />
        </flux:field>

        <div>
            <flux:button type="submit">Create room</flux:button>
            <a href="{{ route('rooms.index', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate>Cancel</a>
        </div>
    </form>
</div>
