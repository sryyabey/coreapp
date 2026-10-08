<?php

namespace App\Models;

use App\Services\SupportAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SupportAgentApp extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function (SupportAgentApp $access): void {
            $user = User::find($access->user_id);
            if (! $user || ! app(SupportAccess::class)->panelUser($user) || ! $user->can('ViewAny:SupportTicket') || ! $user->can('View:SupportTicket')) {
                throw ValidationException::withMessages(['user_id' => 'Önce görevliye panel rolü ve destek görüntüleme izinlerini verin.']);
            }
        });
    }
}
