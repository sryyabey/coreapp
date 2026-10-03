<?php

namespace App\Models;

use Database\Factories\AppUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['app_id', 'user_id', 'is_active', 'joined_at', 'last_seen_at'])]
class AppUser extends Model
{
    /** @use HasFactory<AppUserFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (AppUser $membership): void {
            $membership->joined_at ??= now();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'joined_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
