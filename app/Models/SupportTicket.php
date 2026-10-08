<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SupportTicket extends Model
{
    use HasFactory, HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id', 'app_id', 'user_id'];

    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'category' => 'general', 'version' => 0];

    protected function casts(): array
    {
        return ['user_read_at' => 'datetime', 'last_staff_reply_at' => 'datetime', 'last_customer_message_at' => 'datetime', 'closed_at' => 'datetime', 'due_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (SupportTicket $ticket): void {
            if ($ticket->isDirty(['app_id', 'user_id'])) {
                throw ValidationException::withMessages(['app_id' => 'Destek talebinin uygulaması ve kullanıcısı değiştirilemez.']);
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SupportActivity::class)->orderBy('id');
    }

    public static function priorities(): array
    {
        return ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Yüksek', 'urgent' => 'Acil'];
    }

    public static function categories(): array
    {
        return ['general' => 'Genel', 'bug' => 'Hata', 'account' => 'Hesap', 'billing' => 'Ödeme', 'feature' => 'Öneri'];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }
}
