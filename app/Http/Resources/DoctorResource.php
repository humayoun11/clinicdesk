<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->user->name, 'specialty' => $this->specialty, 'bio' => $this->bio, 'services' => ServiceResource::collection($this->services->where('active', true))];
    }
}
