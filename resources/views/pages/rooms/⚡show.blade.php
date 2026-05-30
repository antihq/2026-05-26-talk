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

        $this->dispatch('message-sent');
    }
}; ?>

<div
    class="max-w-2xl"
    wire:poll.5s
    x-data="{
        nearBottom: true,

        init() {
            this.scrollToBottom()
            this.setupScrollDetector()
            this.setupMutationObserver()
            window.addEventListener('message-sent', () => {
                this.nearBottom = true
                this.scrollToBottom()
            })
        },

        setupScrollDetector() {
            window.addEventListener('scroll', () => {
                this.nearBottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 100
            }, { passive: true })
        },

        setupMutationObserver() {
            const el = this.$el.querySelector('[role=list]')
            const observer = new MutationObserver(() => {
                if (this.nearBottom) {
                    this.scrollToBottom()
                }
            })
            observer.observe(el, { childList: true, subtree: true })
        },

        scrollToBottom() {
            this.$nextTick(() => {
                window.scrollTo(0, document.body.scrollHeight)
            })
        }
    }"
>
    <ul role="list" class="divide-y divide-zinc-950/5 dark:divide-white/5 pb-40">
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
            <li class="py-8 text-center text-zinc-500 dark:text-zinc-400">No messages yet</li>
        @endforelse
    </ul>

    <div x-cloak x-show="!nearBottom" x-transition class="fixed bottom-24 right-4 z-10">
        <flux:button size="xs" variant="filled" @click="scrollToBottom(); nearBottom = true;" class="lowercase shadow-lg">
            jump to latest
        </flux:button>
    </div>

    <div class="sticky bottom-0 bg-white dark:bg-zinc-900 py-4 border-t border-zinc-950/5 dark:border-white/5">
        <div class="flex items-center gap-x-3">
            <flux:heading level="1" class="lowercase"># {{ $room->name }}</flux:heading>
        </div>

        <form wire:submit="sendMessage" class="mt-2">
            <flux:field>
                <flux:input wire:model="body" autocomplete="off" autofocus />
            </flux:field>
            <div class="mt-4 flex justify-end">
                <flux:button type="submit" variant="primary" color="lime" class="lowercase">say it</flux:button>
            </div>
        </form>
    </div>
</div>

