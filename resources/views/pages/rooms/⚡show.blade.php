<?php

use App\Events\MessageSent;
use App\Events\UnreadRoomUpdated;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomMembership;
use App\Notifications\NewMessage;
use App\Services\RoomPresence;
use Illuminate\Support\Facades\Cache;
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

    public function getListeners()
    {
        return [
            'echo-private:user.' . auth()->id() . ',UnreadRoomUpdated' => '$refresh',
        ];
    }

    public function mount(): void
    {
        $this->authorize('view', $this->room);

        RoomMembership::updateOrCreate(
            ['user_id' => auth()->id(), 'room_id' => $this->room->id],
            ['last_read_at' => now()],
        );
    }

    #[Renderless]
    public function away(): void
    {
        Cache::put(
            "room:{$this->room->id}:away:" . auth()->id(),
            true,
            now()->addMinutes(5),
        );
    }

    #[Renderless]
    public function back(): void
    {
        Cache::forget("room:{$this->room->id}:away:" . auth()->id());
    }

    #[On('echo-presence:room.{room.id},MessageSent')]
    public function refreshMessages(): void
    {
        RoomMembership::updateOrCreate(
            ['user_id' => auth()->id(), 'room_id' => $this->room->id],
            ['last_read_at' => now()],
        );
    }

    public function getUnreadRoomsCountProperty()
    {
        return auth()->user()->currentTeam->rooms()
            ->withCount('messages')
            ->withMax('messages', 'created_at')
            ->with(['roomMemberships' => fn ($q) => $q->where('user_id', auth()->id())])
            ->get()
            ->filter(fn ($room) => $room->isUnreadFor(auth()->user()))
            ->count();
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

        RoomMembership::updateOrCreate(
            ['user_id' => auth()->id(), 'room_id' => $this->room->id],
            ['last_read_at' => now()],
        );

        $subscribedIds = app(RoomPresence::class)->subscribedUserIds($this->room);

        $otherMembers = $this->room->team->members()
            ->where('user_id', '!=', auth()->id())
            ->get();

        $awayIds = $otherMembers->filter(
            fn ($member) => Cache::has("room:{$this->room->id}:away:{$member->id}"),
        )->pluck('id')->toArray();

        $skipIds = array_values(array_diff($subscribedIds, $awayIds));

        $disconnectedMembers = $otherMembers->reject(fn ($member) => in_array($member->id, $skipIds));

        Notification::send($disconnectedMembers, new NewMessage(
            room: $this->room,
            sender: auth()->user(),
            body: $message->body,
        ));

        foreach ($otherMembers as $member) {
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
                    this.$nextTick(() => {
                        this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'))
                    })
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
                    'flex flex-col items-start' => $message->user_id !== auth()->id(),
                    'pt-2 pb-2 border-t border-zinc-950/5 dark:border-white/5',
                    'data-threaded:pt-0.5 data-threaded:pb-0 data-threaded:border-t-0',
                    'has-[+[data-threaded]]:pb-0',
                    'data-threaded:not-has-[+[data-threaded]]:pb-2',
                ])
            >
                @if (!$message->isThreaded)
                    <div class="flex items-center gap-x-1.5">
                        @if ($message->user_id === auth()->id())
                            <time class="lowercase text-sm/6 sm:text-xs/6"
                                  datetime="{{ $message->created_at->toISOString() }}"
                                  x-text="localTime($el.getAttribute('datetime'))"
                            >{{ $message->created_at->format('g:i A') }}</time>
                            <p class="font-semibold">{{ $message->user->name }}</p>
                        @else
                            <p class="font-semibold">{{ $message->user->name }}</p>
                            <time class="lowercase text-sm/6 sm:text-xs/6"
                                  datetime="{{ $message->created_at->toISOString() }}"
                                  x-text="localTime($el.getAttribute('datetime'))"
                            >{{ $message->created_at->format('g:i A') }}</time>
                        @endif
                    </div>
                @endif
                <p class="whitespace-pre-line bg-lime-400/20 rounded-md dark:bg-white/2.5 px-1.5 py-0.5">{{ $message->body }}</p>
            </li>
        @endforeach
    </ul>

    <div x-cloak x-show="!nearBottom" x-transition class="fixed bottom-32 right-4 z-10 bg-white dark:bg-zinc-900">
        <flux:button size="xs" variant="filled" @click="scrollToBottom(); nearBottom = true;" class="lowercase shadow-lg">
            jump to latest
        </flux:button>
    </div>

    <div class="sticky bottom-0 pb-4 pt-2 bg-white dark:bg-zinc-900 -mb-4">
        <form wire:submit="sendMessage">
            <flux:composer wire:model="body" label="message" rows="1" placeholder="message" inline label:sr-only>
                <x-slot name="actionsTrailing">
                    <flux:button type="submit" variant="primary" color="lime" class="lowercase">send</flux:button>
                </x-slot>
            </flux:composer>
        </form>
        <div class="flex justify-between flex-wrap gap-x-3 mt-2">
            <div class="flex items-center gap-x-3">
                <flux:heading level="1" class="lowercase"># {{ $room->name }}</flux:heading>
                @can('update', $room)
                    <flux:link href="{{ route('rooms.edit', ['current_team' => auth()->user()->currentTeam->slug, 'room' => $room]) }}" wire:navigate>edit</flux:link>
                @endcan
            </div>
            <div>
                <flux:link :href="route('rooms.index')" wire:navigate class="lowercase">
                    all rooms
                </flux:link>
                @if ($this->unreadRoomsCount > 0)
                    <small class="text-sm/6 sm:text-xs/6">({{ $this->unreadRoomsCount }} unread)</small>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        $wire.away()
    } else {
        $wire.back()
    }
})
</script>
