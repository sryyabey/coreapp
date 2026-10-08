<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'sender_type' => $this->sender_type, 'body' => $this->body, 'created_at' => $this->created_at?->toISOString()];
    }
}
