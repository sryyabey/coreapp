<?php

namespace App\Http\Resources\Api\ShiftCal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'starts_at' => $this->starts_at?->toIso8601String(), 'ends_at' => $this->ends_at?->toIso8601String(), 'timezone' => $this->timezone, 'color' => $this->color, 'note' => $this->note, 'duration_minutes' => (int) $this->starts_at->diffInMinutes($this->ends_at), 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
