<?php

namespace App\Http\Requests\Api\ShiftCal;

use App\Rules\OffsetDateTime;
use Illuminate\Foundation\Http\FormRequest;

class CloudBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:1'],
            'revision' => ['required', 'integer', 'min:0'],
            'user_id' => ['prohibited'], 'app_id' => ['prohibited'],
            'entries' => ['present', 'array', 'max:10000'],
            'entries.*' => ['array:id,date_key,type,start,end,color,note,timezone,starts_at,ends_at'],
            'entries.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'entries.*.date_key' => ['required', 'date_format:Y-m-d'],
            'entries.*.type' => ['required', 'in:work,freeTime,sleep,duty,exercise,other'],
            'entries.*.start' => ['required', 'integer', 'between:0,1439'],
            'entries.*.end' => ['required', 'integer', 'between:0,1439'],
            'entries.*.color' => ['required', 'integer', 'between:0,4294967295'],
            'entries.*.note' => ['nullable', 'string', 'max:5000'],
            'entries.*.timezone' => ['nullable', 'required_with:entries.*.starts_at,entries.*.ends_at', 'timezone:all'],
            'entries.*.starts_at' => ['nullable', 'required_with:entries.*.ends_at', new OffsetDateTime],
            'entries.*.ends_at' => ['nullable', 'required_with:entries.*.starts_at', new OffsetDateTime, 'after:entries.*.starts_at'],
        ];
    }
}
