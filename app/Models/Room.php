<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'created_by'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function roomMemberships(): HasMany
    {
        return $this->hasMany(RoomMembership::class);
    }

    public function isUnreadFor(User $user): bool
    {
        if ($this->messages_count === 0) {
            return false;
        }

        $membership = $this->roomMemberships
            ->firstWhere('user_id', $user->id);

        if (!$membership || !$membership->last_read_at) {
            return true;
        }

        $latestMessageAt = $this->messages_max_created_at;

        return $latestMessageAt && $membership->last_read_at->lessThan($latestMessageAt);
    }

    public function scopeUnreadFor(Builder $query, User $user): void
    {
        $query
            ->whereHas('messages')
            ->where(function (Builder $q) use ($user) {
                $q->whereDoesntHave('roomMemberships', fn ($q) => $q->where('user_id', $user->id))
                  ->orWhereHas('roomMemberships', fn ($q) => $q->where('user_id', $user->id)
                      ->where(function ($q) {
                          $q->whereNull('last_read_at')
                            ->orWhere('last_read_at', '<', Message::selectRaw('max(created_at)')
                                ->whereColumn('room_id', 'rooms.id')
                                ->take(1)
                            );
                      })
                  );
            });
    }

    public function scopeForTeam(Builder $query, Team|int $team): void
    {
        $query->where('team_id', match (true) {
            $team instanceof Team => $team->id,
            default => $team,
        });
    }
}
