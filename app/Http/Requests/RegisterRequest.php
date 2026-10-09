<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:254', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()], 'role' => ['prohibited']];
    }
}
