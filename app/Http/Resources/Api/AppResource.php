<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'slug' => $this->slug, 'name' => $this->name, 'support_email' => $this->support_email, 'website_url' => $this->website_url, 'privacy_policy_url' => $this->privacy_policy_url, 'terms_url' => $this->terms_url];
    }
}
