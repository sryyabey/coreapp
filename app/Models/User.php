<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function appMemberships(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'manage') {
            return false;
        }

        foreach (['super_admin', 'panel_user'] as $roleType) {
            $roleName = config('filament-shield.'.$roleType.'.name');
            if (config('filament-shield.'.$roleType.'.enabled') && is_string($roleName) && $roleName !== '' && $this->hasRole($roleName, 'web')) {
                return true;
            }
        }

        return false;
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
