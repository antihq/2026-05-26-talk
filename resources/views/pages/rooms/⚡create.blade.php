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
    <flux:heading level="1" class="lowercase">Create a new room</flux:heading>

    <form wire:submit="create" class="mt-2">
        <flux:field>
            <flux:label class="lowercase">Room name</flux:label>
            <flux:input wire:model="name" type="text" required autofocus />
            <flux:error name="name" />
        </flux:field>

        <div class="mt-4">
            <flux:button type="submit" variant="primary" color="lime" class="lowercase">Create room</flux:button>
        </div>
    </form>
</div>
