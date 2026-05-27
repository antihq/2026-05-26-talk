<div class="flex h-[calc(100vh-8rem)] -mx-4 -mb-4" x-data="{ autoScroll: true, notificationStatus: 'loading' }" x-init="
    $el.querySelector('.messages-container')?.addEventListener('scroll', function() { autoScroll = this.scrollTop + this.clientHeight >= this.scrollHeight - 50 });

    if (window.getSubscriptionStatus) {
        window.getSubscriptionStatus().then(function(status) { notificationStatus = status; });
    }
" wire:poll.5s>
    <div class="w-72 shrink-0 border-r border-zinc-200 dark:border-zinc-700 flex flex-col bg-zinc-50 dark:bg-zinc-900/50">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Rooms</h2>
        </div>

        <div class="flex-1 overflow-y-auto">
            @foreach ($this->rooms as $room)
                <button wire:click="selectRoom({{ $room->id }})"
                    class="w-full text-left px-4 py-2.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors {{ $this->selectedRoomId === $room->id ? 'bg-zinc-100 dark:bg-zinc-800 border-l-2 border-blue-500' : 'border-l-2 border-transparent' }}">
                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100"># {{ $room->name }}</div>
                    @if ($room->description)
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ $room->description }}</div>
                    @endif
                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">{{ $room->messages_count }} messages</div>
                </button>
            @endforeach
        </div>

        @can('create', App\Models\Room::class)
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                @if ($showCreateForm)
                    <form wire:submit="createRoom" class="space-y-2">
                        <input wire:model="newRoomName" placeholder="Room name" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input wire:model="newRoomDescription" placeholder="Description (optional)" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div class="flex gap-2">
                            <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors">Create</button>
                            <button type="button" wire:click="$set('showCreateForm', false)" class="rounded border border-zinc-300 dark:border-zinc-600 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">Cancel</button>
                        </div>
                    </form>
                @else
                    <button wire:click="$set('showCreateForm', true)" class="w-full flex items-center gap-2 rounded border border-dashed border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs font-medium text-zinc-500 dark:text-zinc-400 hover:border-zinc-400 dark:hover:border-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                        + New room
                    </button>
                @endif
            </div>
        @endcan

        <div class="p-3 border-t border-zinc-200 dark:border-zinc-700">
            <template x-if="notificationStatus === 'loading'">
                <span class="text-xs text-zinc-400">Notifications...</span>
            </template>
            <template x-if="notificationStatus === 'subscribed'">
                <span class="text-xs text-green-600 dark:text-green-400">Notifications on</span>
            </template>
            <template x-if="notificationStatus === 'denied'">
                <span class="text-xs text-zinc-400">Notifications blocked</span>
            </template>
            <template x-if="notificationStatus === 'unsupported'">
                <span class="text-xs text-zinc-400">Push not supported</span>
            </template>
            <template x-if="notificationStatus === 'unsubscribed'">
                <button @click="
                    if (window.subscribeToPush) {
                        window.subscribeToPush().then(function(success) {
                            if (success) { notificationStatus = 'subscribed'; } else { notificationStatus = 'unsubscribed'; }
                        });
                    }
                " class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                    Enable notifications
                </button>
            </template>
        </div>
    </div>

    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-900">
        @if ($this->selectedRoom && $this->selectedRoom->id === $this->selectedRoomId)
            <div class="border-b border-zinc-200 dark:border-zinc-700 px-6 py-3 bg-white dark:bg-zinc-900">
                <h3 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100"># {{ $this->selectedRoom->name }}</h3>
                @if ($this->selectedRoom->description)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $this->selectedRoom->description }}</p>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto messages-container space-y-0" x-ref="messages"
                x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                x-effect="autoScroll && $nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })">
                @forelse ($this->selectedRoom->messages->reverse() as $message)
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
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <h2 class="text-lg font-semibold text-zinc-700 dark:text-zinc-300">Welcome to Campfire</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Select a room from the sidebar to start chatting</p>
                </div>
            </div>
        @endif
    </div>
</div>
