<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'in:ios,android'],
            'transaction_id' => ['required_if:platform,ios', 'prohibited_if:platform,android', 'string', 'regex:/^[0-9]+$/', 'max:100'],
            'purchase_token' => ['required_if:platform,android', 'prohibited_if:platform,ios', 'string', 'max:4096'],
            'environment' => ['sometimes', 'in:production,sandbox'],
            'app_id' => ['prohibited'], 'user_id' => ['prohibited'], 'is_active' => ['prohibited'], 'expires_at' => ['prohibited'],
        ];
    }
}
