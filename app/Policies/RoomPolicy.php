<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Room $room): bool
    {
        return $user->belongsToTeam($room->team);
    }

    public function create(User $user): bool
    {
        $team = $user->currentTeam;
        $role = $user->teamRole($team);

        return $role !== null && $role->isAtLeast(TeamRole::Admin);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->belongsToTeam($room->team)
            && $user->teamRole($room->team)?->isAtLeast(TeamRole::Admin);
    }
}
