<?php

use App\Models\Room;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Edit Room')] class extends Component
{
    public Room $room;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('update', $this->room);

        $this->name = $this->room->name;
    }

    public function update(): void
    {
        $this->authorize('update', $this->room);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->room->update([
            'name' => $this->name,
        ]);

        Flux::toast(variant: 'success', text: 'Room updated.');

        $this->redirectRoute('rooms.show', [
            'current_team' => auth()->user()->currentTeam->slug,
            'room' => $this->room,
        ], navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->room);

        $this->room->delete();

        $this->redirectRoute('rooms.index', [
            'current_team' => auth()->user()->currentTeam->slug,
        ], navigate: true);
    }
}; ?>

<div>
    <flux:heading level="1" class="lowercase">Edit room</flux:heading>

    <form wire:submit="update" class="mt-2">
        <flux:field>
            <flux:label class="lowercase">Room name</flux:label>
            <flux:input wire:model="name" type="text" required autofocus />
            <flux:error name="name" />
        </flux:field>

        <div class="mt-4">
            <flux:button type="submit" variant="primary" color="lime" class="lowercase">Update room</flux:button>
        </div>
    </form>

    <div class="mt-8">
        <flux:heading class="lowercase" level="2">Delete room</flux:heading>

        <flux:button wire:click="delete" wire:confirm="Delete this room?" variant="danger" class="mt-4 lowercase">
            Delete room
        </flux:button>
    </div>
</div>
