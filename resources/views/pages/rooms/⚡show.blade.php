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
            <flux:button size="xs" variant="filled" x-on:click="$el.closest('section').querySelector('input')?.focus()" class="lowercase">chat</flux:button>
        </div>

        <ul role="list" class="messages divide-y divide-zinc-950/5 dark:divide-white/5" x-ref="messages"
            x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
            x-effect="autoScroll && $nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })">
            @forelse ($this->messages as $message)
                <li @class([
                    'py-2',
                    'flex flex-col items-end' => $message->user_id === auth()->id(),
                ])>
                    <div class="flex items-center gap-x-3">
                        @if ($message->user_id === auth()->id())
                            <span class="text-sm/5 sm:text-xs/5">{{ $message->created_at->format('g:i A') }}</span>
                            <p class="font-semibold">{{ $message->user->name }}</p>
                        @else
                            <p class="font-semibold">{{ $message->user->name }}</p>
                            <span class="text-sm/5 sm:text-xs/5">{{ $message->created_at->format('g:i A') }}</span>
                        @endif
                    </div>
                    <p>{{ $message->body }}</p>
                </li>
            @empty
                <li class="py-2"><p>No messages yet.</p></li>
            @endforelse
        </ul>

        <form wire:submit="sendMessage" class="mt-8">
            <flux:field>
                <flux:label class="lowercase">say something</flux:label>
                <flux:input wire:model="body" autocomplete="off" />
            </flux:field>

            <div class="mt-4 flex justify-end">
                <flux:button type="submit" variant="primary" color="lime" class="lowercase">say it</flux:button>
            </div>
        </form>
    </section>
</div>
