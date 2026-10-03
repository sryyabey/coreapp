<?php

namespace App\Http\Requests\Api\ShiftCal;

use App\Models\ShiftCal\Event;
use App\Rules\OffsetDateTime;
use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('event')) {
            $record = Event::where('app_id', $this->attributes->get('mobile_app')->id)->where('user_id', $this->user()->id)->whereKey($this->route('event'))->firstOrFail();
            $existing = $record->only(['type', 'starts_at', 'ends_at', 'timezone', 'color', 'note']);
            $existing['starts_at'] = $record->starts_at->toIso8601String();
            $existing['ends_at'] = $record->ends_at->toIso8601String();
            $this->mergeIfMissing($existing);
        }
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:work,freeTime,sleep,duty,exercise,other'], 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'note' => ['nullable', 'string', 'max:5000'], 'app_id' => ['prohibited'], 'user_id' => ['prohibited'], 'id' => ['prohibited'], 'starts_at' => ['required', new OffsetDateTime], 'ends_at' => ['required', new OffsetDateTime, 'after:starts_at'], 'timezone' => ['required', 'timezone:all']];
    }
}
