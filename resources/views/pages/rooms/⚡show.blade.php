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

<div class="flex h-[calc(100vh-8rem)] -mx-4 -mb-4" x-data="{ autoScroll: true }" wire:poll.5s
    x-init="
        $el.querySelector('.messages-container')?.addEventListener('scroll', function() {
            autoScroll = this.scrollTop + this.clientHeight >= this.scrollHeight - 50
        });
    ">
    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-900">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-6 py-3 bg-white dark:bg-zinc-900">
            <a href="{{ route('rooms.index', ['current_team' => auth()->user()->currentTeam->slug]) }}" wire:navigate class="text-xs text-blue-600 dark:text-blue-400 hover:underline mb-1 inline-block lowercase">&larr; all rooms</a>
            <h3 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100"># {{ $room->name }}</h3>
            @if ($room->description)
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $room->description }}</p>
            @endif
        </div>

        <div class="flex-1 overflow-y-auto messages-container space-y-0" x-ref="messages"
            x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
            x-effect="autoScroll && $nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })">
            @forelse ($this->messages as $message)
                <div class="px-6 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $message->user->name }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->format('g:i A') }}</span>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 mt-0.5 whitespace-pre-wrap">{{ $message->body }}</p>
                </div>
            @empty
                <div class="flex items-center justify-center h-full">
                    <p class="text-sm text-zinc-400 dark:text-zinc-500">No messages yet. Say something!</p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-zinc-200 dark:border-zinc-700 px-6 py-4 bg-white dark:bg-zinc-900">
            <form wire:submit="sendMessage" class="flex gap-3">
                <input wire:model="body" placeholder="Type a message..." autocomplete="off"
                    class="flex-1 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800 px-4 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors disabled:opacity-50" {{ empty($body) ? 'disabled' : '' }}>
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
