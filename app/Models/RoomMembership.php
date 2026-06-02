<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMembership extends Model
{
    protected $table = 'room_memberships';

    protected $fillable = ['user_id', 'room_id', 'last_read_at', 'connected_at'];

    const CONNECTION_TTL = 60;

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function scopeConnected($query)
    {
        $query->where('connected_at', '>=', now()->subSeconds(static::CONNECTION_TTL));
    }

    public function scopeDisconnected($query)
    {
        $query->whereNull('connected_at')
            ->orWhere('connected_at', '<', now()->subSeconds(static::CONNECTION_TTL));
    }

    public static function disconnectAll(): void
    {
        static::where('connected_at', '>=', now()->subSeconds(static::CONNECTION_TTL))
            ->update(['connected_at' => null]);
    }

    public static function present(User $user, Room $room): RoomMembership
    {
        $membership = static::updateOrCreate(
            ['user_id' => $user->id, 'room_id' => $room->id],
        );

        $membership->updateQuietly([
            'connected_at' => now(),
            'last_read_at' => now(),
        ]);

        static::where('user_id', $user->id)
            ->where('room_id', '!=', $room->id)
            ->update(['connected_at' => null]);

        return $membership;
    }

    public function isConnected(): bool
    {
        return $this->connected_at && $this->connected_at >= now()->subSeconds(static::CONNECTION_TTL);
    }

    public function markDisconnected(): void
    {
        $this->updateQuietly(['connected_at' => null]);
    }

    public function refreshConnection(): void
    {
        $this->touchQuietly('connected_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
