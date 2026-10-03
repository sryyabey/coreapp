<?php

namespace App\Http\Resources\Api\ShiftCal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WageSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'hourly_rate' => $this->hourly_rate, 'overtime_multiplier' => $this->overtime_multiplier, 'currency' => $this->currency, 'weekly_target_minutes' => $this->weekly_target_minutes, 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
