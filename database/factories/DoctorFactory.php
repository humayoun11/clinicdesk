<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->state(['role' => 'doctor']), 'specialty' => 'Family Medicine', 'bio' => 'Fictional demonstration clinician.', 'active' => true];
    }
}
