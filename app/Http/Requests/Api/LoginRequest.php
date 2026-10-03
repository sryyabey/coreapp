<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'user_id' => ['prohibited'],
            'app_id' => ['prohibited'],
            'tokenable_id' => ['prohibited'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:ios,android'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'E-posta adresinizi girin.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'password.required' => 'Şifrenizi girin.',
        ];
    }
}
