<?php

namespace App\Http\Resources\Api\ShiftCal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'start_time' => $this->start_time, 'end_time' => $this->end_time, 'end_day_offset' => $this->end_day_offset, 'color' => $this->color, 'note' => $this->note, 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
