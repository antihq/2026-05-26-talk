<?php

use App\Events\MessageSent;
use App\Events\UnreadRoomUpdated;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomMembership;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

        RoomMembership::present(auth()->user(), $this->room);
    }

    #[On('echo-private:room.{room.id},MessageSent')]
    public function refreshMessages(): void
    {
        //
    }

    public function getMessagesProperty()
    {
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
    public function refresh(): void
    {
        $membership = RoomMembership::where('user_id', auth()->id())
            ->where('room_id', $this->room->id)
            ->first();

        $membership?->refreshConnection();
    }

    #[Renderless]
    public function absent(): void
    {
        $membership = RoomMembership::where('user_id', auth()->id())
            ->where('room_id', $this->room->id)
            ->first();

        $membership?->markDisconnected();
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

        broadcast(new MessageSent($message));

        $connectedUserIds = RoomMembership::where('room_id', $this->room->id)
            ->connected()
            ->pluck('user_id');

        $disconnectedMembers = $this->room->team->members()
            ->where('user_id', '!=', auth()->id())
            ->whereNotIn('user_id', $connectedUserIds)
            ->get();

        Notification::send($disconnectedMembers, new NewMessage(
            room: $this->room,
            sender: auth()->user(),
            body: $message->body,
        ));

        foreach ($this->room->team->members()->where('user_id', '!=', auth()->id())->get() as $member) {
            broadcast(new UnreadRoomUpdated($this->room, $member));
        }

        $this->reset('body');

        $this->dispatch('message-sent');
    }
}; ?>

    <div
        class="max-w-2xl"
        data-room-id="{{ $room->id }}"
        x-data="{
            nearBottom: true,

            init() {
                navigator.clearAppBadge?.();

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
    <ul role="list">
        @foreach ($this->messages as $message)
            <li
                @if($message->isThreaded) data-threaded @endif
                @class([
                    'flex flex-col items-end' => $message->user_id === auth()->id(),
                    'pt-2 pb-2 border-t border-zinc-950/5 dark:border-white/5',
                    'data-threaded:pt-0 data-threaded:pb-0 data-threaded:border-t-0',
                    'has-[+[data-threaded]]:pb-0',
                    'data-threaded:not-has-[+[data-threaded]]:pb-2',
                ])
            >
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
            <flux:button :href="route('rooms.index')" size="xs" variant="filled" wire:navigate>switch room</flux:button>
            @can('update', $room)
                <flux:link href="{{ route('rooms.edit', ['current_team' => auth()->user()->currentTeam->slug, 'room' => $room]) }}" wire:navigate>edit</flux:link>
            @endcan
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

<script>
(() => {
    const roomId = $wire.$el.dataset.roomId
    const ac = new AbortController()
    let refreshTimer = null
    let wasVisible = true

    function startRefreshTimer() {
        if (refreshTimer) return
        refreshTimer = setInterval(() => $wire.refresh(), 50000)
    }

    function stopRefreshTimer() {
        clearInterval(refreshTimer)
        refreshTimer = null
    }

    function absentFetch() {
        fetch('/presence/' + roomId + '/absent', {
            method: 'POST',
            keepalive: true,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
        })
    }

    function cleanup() {
        stopRefreshTimer()
        ac.abort()
        pusher.connection.unbind('connected', connectedHandler)
        pusher.connection.unbind('disconnected', disconnectedHandler)
    }

    const pusher = Echo.connector.pusher

    if (pusher.connection.state === 'connected') {
        startRefreshTimer()
    }

    const connectedHandler = () => {
        if (document.visibilityState === 'visible') {
            $wire.refresh()
            startRefreshTimer()
        }
    }

    const disconnectedHandler = () => {
        stopRefreshTimer()
        absentFetch()
    }

    pusher.connection.bind('connected', connectedHandler)
    pusher.connection.bind('disconnected', disconnectedHandler)

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            setTimeout(() => {
                if (document.visibilityState === 'visible' && !wasVisible) {
                    $wire.refresh()
                    startRefreshTimer()
                    wasVisible = true
                }
            }, 5000)
        } else {
            absentFetch()
            stopRefreshTimer()
            wasVisible = false
        }
    }, { signal: ac.signal })

    window.addEventListener('beforeunload', absentFetch, { signal: ac.signal })

    document.addEventListener('livewire:navigating', () => {
        absentFetch()
        cleanup()
    }, { signal: ac.signal })
})()
</script>
