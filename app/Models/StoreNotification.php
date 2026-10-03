<?php

namespace App\Models;

use Database\Factories\StoreNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_app_id', 'event_id', 'type', 'environment', 'identity', 'proof', 'status', 'processed_at', 'attempts', 'last_error'])]
#[Hidden(['proof', 'identity'])]
class StoreNotification extends Model
{
    /** @use HasFactory<StoreNotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['proof' => 'encrypted', 'processed_at' => 'datetime'];
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }
}
