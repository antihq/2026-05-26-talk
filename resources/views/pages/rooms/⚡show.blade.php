<?php

use App\Models\Message;
use App\Models\Room;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Room')] class extends Component
{
    public Room $room;

    public string $body = '';

    public function mount(): void
    {
        $this->authorize('view', $this->room);
    }

    public function getMessagesProperty()
    {
        return $this->room->messages()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->reverse();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $message = Message::create([
            'room_id' => $this->room->id,
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $members = $this->room->team->members()
            ->where('user_id', '!=', auth()->id())
            ->get();

        Notification::send($members, new NewMessage(
            room: $this->room,
            sender: auth()->user(),
            body: $message->body,
        ));

        $this->reset('body');
    }
}; ?>

<div x-data="{ autoScroll: true }" wire:poll.5s
    x-init="
        $el.querySelector('.messages')?.addEventListener('scroll', function() {
            autoScroll = this.scrollTop + this.clientHeight >= this.scrollHeight - 50
        });
    ">
    <section class="max-w-2xl">
        <div class="flex items-center gap-x-3">
            <flux:heading level="1" class="lowercase"># {{ $room->name }}</flux:heading>
            <flux:button size="sm" x-on:click="$el.closest('section').querySelector('input')?.focus()" class="lowercase">message</flux:button>
        </div>

        <div class="messages" x-ref="messages"
            x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
            x-effect="autoScroll && $nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })">
            @forelse ($this->messages as $message)
                <div>
                    <strong>{{ $message->user->name }}</strong>
                    <span>{{ $message->created_at->format('g:i A') }}</span>
                    <p>{{ $message->body }}</p>
                </div>
            @empty
                <p>No messages yet.</p>
            @endforelse
        </div>

        <form wire:submit="sendMessage">
            <flux:input wire:model="body" placeholder="Type a message..." autocomplete="off" />
            <flux:button type="submit">Send</flux:button>
        </form>
    </section>
</div>
