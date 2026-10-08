<?php

namespace App\Http\Requests\Api;

class AppleLoginRequest extends LoginRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['email'], $rules['password']);
        $rules['id_token'] = ['required', 'string', 'max:16384'];
        $rules['challenge_id'] = ['required', 'uuid'];
        $rules['name'] = ['nullable', 'string', 'max:255'];
        $rules['platform'] = ['required', 'in:ios'];

        return $rules;
    }
}
