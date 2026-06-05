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
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Room')] class extends Component
{
    use WithFileUploads;

    public Room $room;

    public string $body = '';

    public $files = [];

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

        $this->broadcastMessage($message);

        $this->reset('body');
    }

    public function updatedFiles(): void
    {
        $this->validate([
            'files.*' => ['file', 'max:10240'],
        ]);

        foreach ($this->files as $file) {
            $path = $file->store('attachments', 'public');

            $message = Message::create([
                'room_id' => $this->room->id,
                'user_id' => auth()->id(),
                'body' => '',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            $this->broadcastMessage($message);
        }

        $this->reset('files');
    }

    private function broadcastMessage(Message $message): void
    {
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

        $body = $message->hasFile() ? $message->file_name : $message->body;

        Notification::send($disconnectedMembers, new NewMessage(
            room: $this->room,
            sender: auth()->user(),
            body: $body,
        ));

        foreach ($otherMembers as $member) {
            broadcast(new UnreadRoomUpdated($this->room, $member));
        }

        $this->dispatch('message-sent');
    }
}; ?>

    <div
        class="max-w-2xl"
        data-room-id="{{ $room->id }}"
        @dragenter.prevent="handleDragEnter($event)"
        @dragover.prevent
        @dragleave="handleDragLeave($event)"
        @drop="handleDrop($event)"
        @paste="handlePaste($event)"
        x-data="{
            nearBottom: true,
            dragging: false,
            dragCounter: 0,
            uploading: false,
            uploadProgress: 0,

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

            uploadFile(file) {
                this.uploading = true
                $wire.upload('files', file,
                    () => { this.uploading = false; this.uploadProgress = 0 },
                    () => { this.uploading = false; this.uploadProgress = 0 },
                    (e) => { this.uploadProgress = e.detail.progress },
                    () => { this.uploading = false; this.uploadProgress = 0 },
                )
            },

            handleDragEnter(e) {
                if (e.dataTransfer.types.includes('Files')) {
                    this.dragCounter++
                    this.dragging = true
                }
            },

            handleDragLeave(e) {
                this.dragCounter--
                if (this.dragCounter <= 0) {
                    this.dragging = false
                    this.dragCounter = 0
                }
            },

            handleDrop(e) {
                e.preventDefault()
                this.dragging = false
                this.dragCounter = 0

                const files = Array.from(e.dataTransfer.files)
                if (files.length === 0) return

                files.forEach(file => this.uploadFile(file))
                this.$nextTick(() => this.scrollToBottom())
            },

            handlePaste(e) {
                const files = Array.from(e.clipboardData?.files ?? [])
                if (files.length === 0) return

                e.preventDefault()
                files.forEach(file => this.uploadFile(file))
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
    <div x-cloak x-show="dragging" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/30 dark:bg-zinc-950/50 backdrop-blur-sm">
        <div class="flex gap-2 border-2 border-dashed border-lime-950 px-14 py-8 bg-lime-300">
            <flux:icon name="cloud-arrow-up" class="size-6 text-lime-950" />
            <p class="text-lime-950 font-semibold">drop files to send</p>
        </div>
    </div>

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

                @if ($message->hasFile() && $message->isImage())
                    <a href="{{ $message->fileUrl() }}" target="_blank" rel="noopener"
                       @class([
                           'rounded-md overflow-hidden max-w-64',
                           'ml-8' => $message->user_id === auth()->id(),
                           'mr-8' => $message->user_id !== auth()->id(),
                       ])
                    >
                        <img src="{{ $message->fileUrl() }}" alt="{{ $message->file_name }}" class="w-full h-auto">
                    </a>
                @elseif ($message->hasFile())
                    <a href="{{ $message->fileUrl() }}" target="_blank" rel="noopener"
                       @class([
                           'flex gap-1.5 rounded-md px-3 py-2 max-w-72',
                           'ml-8 bg-lime-400/20 text-lime-950 dark:bg-lime-400/10 dark:text-lime-200' => $message->user_id === auth()->id(),
                           'mr-8 bg-zinc-600/10 text-zinc-950 dark:bg-white/5 dark:text-zinc-200' => $message->user_id !== auth()->id(),
                       ])
                    >
                        <flux:icon name="document" class="size-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="truncate">{{ $message->file_name }}</p>
                            <p class="text-sm/5 sm:text-xs/5">{{ $message->formattedFileSize() }}</p>
                        </div>
                    </a>
                @else
                    <p
                        @class([
                            'whitespace-pre-line rounded-md px-1.5 py-0.5',
                            'ml-8 bg-lime-400/20 text-lime-950 dark:bg-lime-400/10 dark:text-lime-200' => $message->user_id === auth()->id(),
                            'mr-8 bg-zinc-600/10 text-zinc-950 dark:bg-white/5 dark:text-zinc-200' => $message->user_id !== auth()->id(),
                        ])
                    >{{ $message->body }}</p>
                @endif
            </li>
        @endforeach
    </ul>

    <div x-cloak x-show="!nearBottom" x-transition class="fixed bottom-32 right-4 z-10 bg-white dark:bg-zinc-900">
        <flux:button size="xs" variant="filled" @click="scrollToBottom(); nearBottom = true;" class="lowercase shadow-lg">
            jump to latest
        </flux:button>
    </div>

    <div class="sticky bottom-0 pb-4 pt-2 bg-white dark:bg-zinc-900 -mb-4">
        <div x-cloak x-show="uploading" x-transition class="h-0.5 mb-1 bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
            <div class="h-full bg-lime-300 transition-all duration-150" :style="'width:' + uploadProgress + '%'"></div>
        </div>
        <form wire:submit="sendMessage">
            <flux:composer wire:model="body" label="message" rows="1" placeholder="message" inline label:sr-only>
                <x-slot name="actionsLeading">
                    <flux:file-upload wire:model="files" multiple>
                        <button type="button"
                                class="flex items-center justify-center rounded-md p-1.5 transition-colors cursor-pointer hover:bg-zinc-100 dark:hover:bg-white/10 in-data-dragging:bg-zinc-100 dark:in-data-dragging:bg-white/10"
                                aria-label="Attach files"
                        >
                            <flux:icon name="paper-clip" class="size-5 text-zinc-500 dark:text-zinc-400" />
                        </button>
                    </flux:file-upload>
                </x-slot>
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
