<?php

namespace App\Http\Requests\Api;

class GoogleLoginRequest extends LoginRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['email'], $rules['password']);
        $rules['id_token'] = ['required', 'string', 'max:16384'];

        return $rules;
    }
}
