<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $s = now()->subDay()->setTime(4, 0);

        return ['doctor_id' => Doctor::factory(), 'patient_id' => User::factory(), 'service_id' => Service::factory(), 'starts_at' => $s, 'ends_at' => $s->copy()->addMinutes(30), 'status' => 'completed'];
    }
}
