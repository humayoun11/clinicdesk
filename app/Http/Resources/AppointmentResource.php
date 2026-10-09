<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'patient' => new UserResource($this->patient), 'doctor' => new DoctorResource($this->doctor), 'service' => new ServiceResource($this->service), 'starts_at' => $this->starts_at->toIso8601String(), 'ends_at' => $this->ends_at->toIso8601String(), 'status' => $this->status, 'display_timezone' => config('clinic.timezone')];
    }
}
