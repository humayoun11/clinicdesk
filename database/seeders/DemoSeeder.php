<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Fictional demo seeding is disabled in production.');
        }
        if (! in_array(DB::connection()->getDatabaseName(), ['clinicdesk_demo', 'clinicdesk_test', ':memory:'])) {
            throw new \RuntimeException('DemoSeeder requires an isolated ClinicDesk database.');
        }
        if (User::where('email', 'admin@clinicdesk.example')->exists()) {
            $this->command?->info('Demo users already exist; no data changed.');

            return;
        }
        $password = env('CLINICDESK_DEMO_PASSWORD') ?: Str::password(18);
        DB::transaction(function () use ($password) {
            $admin = User::create(['name' => 'Alex Morgan', 'email' => 'admin@clinicdesk.example', 'password' => $password]);
            $admin->role = 'admin';
            $admin->save();
            $patient = User::create(['name' => 'Jamie Parker', 'email' => 'patient@clinicdesk.example', 'password' => $password]);
            $other = User::create(['name' => 'Taylor Lane', 'email' => 'taylor@clinicdesk.example', 'password' => $password]);
            $services = collect(['General consultation' => 'A focused conversation about everyday health and next steps.', 'Wellness visit' => 'A routine check-in on healthy habits and general wellbeing.', 'Specialist consultation' => 'A conversation with a specialist about your care options.'])->map(fn ($description, $name) => Service::create(compact('name', 'description')))->values();
            foreach ([['Dr. Maya Reed', 'Family Medicine', 'doctor@clinicdesk.example'], ['Dr. Oliver Chen', 'Internal Medicine', 'oliver@clinicdesk.example'], ['Dr. Sofia Brooks', 'Dermatology', 'sofia@clinicdesk.example']] as [$name,$specialty,$email]) {
                $user = User::create(compact('name', 'email', 'password'));
                $user->role = 'doctor';
                $user->save();
                $doctor = Doctor::create(['user_id' => $user->id, 'specialty' => $specialty, 'bio' => 'A fictional clinician who values thoughtful conversations, clear explanations and a welcoming experience.']);
                $doctor->services()->attach($services->pluck('id'));
                for ($day = 1; $day <= 14; $day++) {
                    $start = now(config('clinic.timezone'))->addDays($day)->setTime(9, 0)->utc();
                    Schedule::create(['doctor_id' => $doctor->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHours(7)]);
                }
            }
            $booking = app(BookingService::class);
            $doctor = Doctor::first();
            foreach ([1, 3, 5] as $day) {
                $booking->book($patient, ['doctor_id' => $doctor->id, 'service_id' => $services[0]->id, 'starts_at' => now(config('clinic.timezone'))->addDays($day)->setTime(10, 0)->toIso8601String()]);
            }
            $booking->book($other, ['doctor_id' => $doctor->id, 'service_id' => $services[1]->id, 'starts_at' => now(config('clinic.timezone'))->addDays(2)->setTime(11, 0)->toIso8601String()]);
            Appointment::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $services[0]->id, 'starts_at' => now()->subDays(3)->setTime(5, 0), 'ends_at' => now()->subDays(3)->setTime(5, 30), 'status' => 'completed']);
        });
        $this->command?->warn('Local fictional demo password (all demo accounts): '.$password);
        $this->command?->info('Accounts: admin@clinicdesk.example / doctor@clinicdesk.example / patient@clinicdesk.example');
    }
}
