<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8), function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Şifre UTF-8 kodlamasında en fazla 72 bayt olabilir.');
                }
            }],
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:ios,android'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Adınızı girin.',
            'email.required' => 'E-posta adresinizi girin.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.unique' => 'Bu e-posta adresi zaten kayıtlı.',
            'password.required' => 'Şifrenizi girin.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'password.min' => 'Şifre en az 8 karakter olmalıdır.',
            'password.max' => 'Şifre en fazla 72 karakter olabilir.',
        ];
    }
}
