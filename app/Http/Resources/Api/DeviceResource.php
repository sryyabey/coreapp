<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'platform' => $this->platform,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(), 'revoked_at' => $this->revoked_at?->toIso8601String(),
            'is_current' => $request->attributes->get('mobile_device')->id === $this->id];
    }
}
