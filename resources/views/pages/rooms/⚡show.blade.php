<?php

use App\Models\Message;
use App\Models\Room;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
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
        Cache::put("room:{$this->room->id}:presence:" . auth()->id(), true, 60);

        $messages = $this->room->messages()
            ->with('user')
            ->latest()
            ->limit(40)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(function ($message, $index) use ($messages) {
            $prev = $messages->get($index - 1);
            $message->isThreaded = $prev
                && $prev->user_id === $message->user_id
                && $prev->created_at->diffInSeconds($message->created_at) <= 300;

            return $message;
        });
    }

    #[Renderless]
    public function absent(): void
    {
        Cache::forget("room:{$this->room->id}:presence:" . auth()->id());
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
            ->get()
            ->filter(fn ($member) => !Cache::has("room:{$this->room->id}:presence:{$member->id}"));

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
    x-on:visibilitychange.window="document.visibilityState === 'hidden' && $wire.absent()"
    x-data="{
        nearBottom: true,

        init() {
            this.scrollToBottom()
            this.setupScrollDetector()

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

        scrollToBottom() {
            this.$nextTick(() => {
                window.scrollTo(0, document.body.scrollHeight)
            })
        },

        localTime(iso) {
            const date = new Date(iso)
            const now = new Date()
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
            const yesterday = new Date(today.getTime() - 86400000)
            const msgDay = new Date(date.getFullYear(), date.getMonth(), date.getDate())

            let dayLabel
            if (msgDay.getTime() === today.getTime()) dayLabel = 'today'
            else if (msgDay.getTime() === yesterday.getTime()) dayLabel = 'yesterday'
            else if (date.getFullYear() === now.getFullYear()) dayLabel = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' }).format(date)
            else dayLabel = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date)

            return dayLabel + ' at ' + new Intl.DateTimeFormat(undefined, { timeStyle: 'short' }).format(date)
        }
    }"
>
    <ul role="list" class="divide-y divide-zinc-950/5 dark:divide-white/5">
        @foreach ($this->messages as $message)
            <li @class([
                'py-2',
                'flex flex-col items-end' => $message->user_id === auth()->id(),
            ])>
                @if (!$message->isThreaded)
                    <div class="flex items-center gap-x-3">
                        @if ($message->user_id === auth()->id())
                            <time class="lowercase"
                                  datetime="{{ $message->created_at->toISOString() }}"
                                  x-text="localTime($el.getAttribute('datetime'))"
                            >{{ $message->created_at->format('g:i A') }}</time>
                            <p class="font-semibold">{{ $message->user->name }}</p>
                        @else
                            <p class="font-semibold">{{ $message->user->name }}</p>
                            <time class="lowercase"
                                  datetime="{{ $message->created_at->toISOString() }}"
                                  x-text="localTime($el.getAttribute('datetime'))"
                            >{{ $message->created_at->format('g:i A') }}</time>
                        @endif
                    </div>
                @endif
                <p>{{ $message->body }}</p>
            </li>
        @endforeach
    </ul>

    <div x-cloak x-show="!nearBottom" x-transition class="fixed bottom-24 right-4 z-10">
        <flux:button size="xs" variant="filled" @click="scrollToBottom(); nearBottom = true;" class="lowercase shadow-lg">
            jump to latest
        </flux:button>
    </div>

    <div class="sticky bottom-0 pb-4 pt-2 bg-white dark:bg-zinc-900 -mb-4">
        <div class="flex items-center gap-x-3">
            <flux:heading level="1" class="lowercase"># {{ $room->name }}</flux:heading>
            <flux:button :href="route('rooms.index')" size="xs" variant="filled" wire:navigate x-on:click="$wire.absent()">switch room</flux:button>
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
