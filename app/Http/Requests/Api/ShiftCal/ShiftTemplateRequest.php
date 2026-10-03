<?php

namespace App\Http\Requests\Api\ShiftCal;

use App\Models\ShiftCal\ShiftTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('shift_template')) {
            $record = ShiftTemplate::where('app_id', $this->attributes->get('mobile_app')->id)->where('user_id', $this->user()->id)->whereKey($this->route('shift_template'))->firstOrFail();
            $existing = $record->only(['name', 'type', 'start_time', 'end_time', 'end_day_offset', 'color', 'note']);
            $this->mergeIfMissing($existing);
        }
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:work,freeTime,sleep,duty,exercise,other'], 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'note' => ['nullable', 'string', 'max:5000'], 'app_id' => ['prohibited'], 'user_id' => ['prohibited'], 'id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'], 'end_day_offset' => ['required', 'integer', 'in:0,1']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$startHour, $startMinute] = array_map('intval', explode(':', $this->start_time));
            [$endHour, $endMinute] = array_map('intval', explode(':', $this->end_time));
            $duration = $endHour * 60 + $endMinute + $this->end_day_offset * 1440 - $startHour * 60 - $startMinute;
            if ($duration <= 0 || $duration > 1440) {
                $validator->errors()->add('end_time', 'Vardiya süresi 0 dakikadan fazla ve en çok 24 saat olmalı.');
            }
        }];
    }
}
