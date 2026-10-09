<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['booked', 'cancelled', 'completed', 'no_show'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'], 'date' => ['nullable', 'date_format:Y-m-d']];
    }
}
