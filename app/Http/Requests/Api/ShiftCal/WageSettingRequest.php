<?php

namespace App\Http\Requests\Api\ShiftCal;

use Illuminate\Foundation\Http\FormRequest;

class WageSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'], 'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:999.99', 'decimal:0,2'], 'currency' => ['required', 'regex:/^[A-Z]{3}$/'], 'weekly_target_minutes' => ['required', 'integer', 'min:0', 'max:10080'], 'app_id' => ['prohibited'], 'user_id' => ['prohibited'], 'id' => ['prohibited']];
    }
}
