<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'store_product_id' => $this->store_product_id, 'environment' => $this->environment,
            'status' => $this->status, 'expires_at' => $this->expires_at?->toIso8601String(), 'verified_at' => $this->verified_at->toIso8601String(),
            'is_active' => $this->resource->hasPaidAccess()];
    }
}
