<?php

namespace App\Http\Requests;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return match ($this->route('kind')) {
            'doctors' => ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($this->route('id') ? Doctor::findOrFail($this->route('id'))->user_id : null)], 'password' => [$this->route('id') ? 'nullable' : 'required', Password::min(10)->mixedCase()->numbers()], 'specialty' => ['required', 'string', 'max:120'], 'bio' => ['required', 'string', 'max:2000'], 'active' => ['required', 'boolean'], 'services' => ['required', 'array', 'min:1'], 'services.*' => ['integer', 'exists:services,id']],
            'services' => ['name' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:2000'], 'active' => ['required', 'boolean']],
            'schedules' => ['doctor_id' => ['required', 'integer', 'exists:doctors,id'], 'starts_at' => ['required', 'date', 'max:40'], 'ends_at' => ['required', 'date', 'max:40']],
            default => ['invalid_kind' => ['required']]
        };
    }
}
