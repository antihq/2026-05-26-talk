<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['room_id', 'user_id', 'body', 'file_path', 'file_name', 'file_type', 'file_size'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    public function fileUrl(): ?string
    {
        if (!$this->hasFile()) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    public function isImage(): bool
    {
        return $this->hasFile() && str_starts_with($this->file_type, 'image/');
    }

    public function formattedFileSize(): string
    {
        if (!$this->file_size) {
            return '';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
