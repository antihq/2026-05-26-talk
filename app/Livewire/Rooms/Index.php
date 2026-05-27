<?php

namespace App\Livewire\Rooms;

use App\Models\Message;
use App\Models\Room;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'room', history: true)]
    public ?int $selectedRoomId = null;

    public string $newRoomName = '';

    public ?string $newRoomDescription = null;

    public bool $showCreateForm = false;

    public string $body = '';

    public function getRoomsProperty()
    {
        return auth()->user()->currentTeam->rooms()
            ->withCount('messages')
            ->orderBy('name')
            ->get();
    }

    public function getSelectedRoomProperty(): ?Room
    {
        if (! $this->selectedRoomId) {
            return null;
        }

        $room = Room::with(['messages' => function ($query) {
            $query->with('user')->latest()->limit(100);
        }])->find($this->selectedRoomId);

        if ($room && ! auth()->user()->belongsToTeam($room->team)) {
            $this->selectedRoomId = null;

            return null;
        }

        return $room;
    }

    public function selectRoom(int $roomId): void
    {
        $room = Room::findOrFail($roomId);
        $this->authorize('view', $room);
        $this->selectedRoomId = $roomId;
    }

    public function createRoom(): void
    {
        $this->authorize('create', Room::class);

        $this->validate([
            'newRoomName' => ['required', 'string', 'max:255'],
        ]);

        $room = Room::create([
            'team_id' => auth()->user()->currentTeam->id,
            'name' => $this->newRoomName,
            'description' => $this->newRoomDescription,
            'created_by' => auth()->id(),
        ]);

        $this->reset('newRoomName', 'newRoomDescription', 'showCreateForm');
        $this->selectedRoomId = $room->id;
    }

    public function sendMessage(): void
    {
        if (! $this->selectedRoomId) {
            return;
        }

        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $message = Message::create([
            'room_id' => $this->selectedRoomId,
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $room = $this->selectedRoom;

        if ($room) {
            $members = $room->team->members()
                ->where('user_id', '!=', auth()->id())
                ->get();

            Notification::send($members, new NewMessage(
                room: $room,
                sender: auth()->user(),
                body: $message->body,
            ));
        }

        $this->reset('body');
    }

    public function render()
    {
        return view('livewire.rooms.index');
    }
}
