<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['doctor_id' => ['required', 'integer', 'exists:doctors,id'], 'service_id' => ['required', 'integer', 'exists:services,id'], 'starts_at' => ['required', 'date', 'max:40'], 'patient_id' => ['prohibited'], 'status' => ['prohibited']];
    }
}
