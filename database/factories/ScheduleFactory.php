<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $s = now()->addDay()->setTime(4, 0);

        return ['doctor_id' => Doctor::factory(), 'starts_at' => $s, 'ends_at' => $s->copy()->addHours(4)];
    }
}
