<?php

use App\Models\Room;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Create Room')] class extends Component
{
    public string $name = '';

    public ?string $description = null;

    public function create(): void
    {
        $this->authorize('create', Room::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $room = Room::create([
            'team_id' => auth()->user()->currentTeam->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => auth()->id(),
        ]);

        $this->redirectRoute('rooms.show', [
            'current_team' => auth()->user()->currentTeam->slug,
            'room' => $room,
        ], navigate: true);
    }
}; ?>

<div class="max-w-lg">
    <flux:heading level="1" class="lowercase">Create a new room</flux:heading>

    <form wire:submit="create" class="mt-6">
        <flux:fieldset>
            <flux:field class="max-w-sm">
                <flux:label class="lowercase">Room name</flux:label>
                <flux:input wire:model="name" type="text" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field class="mt-4 max-w-sm">
                <flux:label class="lowercase">Description</flux:label>
                <flux:textarea wire:model="description" />
                <flux:error name="description" />
            </flux:field>
        </flux:fieldset>

        <div class="mt-6 flex gap-3">
            <flux:button type="submit" variant="primary" color="lime" class="lowercase">Create room</flux:button>
            <a href="{{ route('rooms.index', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate class="text-sm text-zinc-500 dark:text-zinc-400 hover:underline self-center lowercase">Cancel</a>
        </div>
    </form>
</div>
