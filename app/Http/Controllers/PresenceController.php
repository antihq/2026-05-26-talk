<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomMembership;
use Illuminate\Support\Facades\Gate;

class PresenceController extends Controller
{
    public function absent(Room $room)
    {
        Gate::authorize('view', $room);

        $membership = RoomMembership::where('user_id', auth()->id())
            ->where('room_id', $room->id)
            ->first();

        $membership?->markDisconnected();

        return response()->noContent();
    }
}
