<?php

namespace App\Models;

use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active', 'support_email', 'website_url', 'privacy_policy_url', 'terms_url'])]
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function appMemberships(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function storeApps(): HasMany
    {
        return $this->hasMany(StoreApp::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class);
    }
}
