<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('clinic.timezone'))->utc();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['starts_at' => $message]);
    }

    private function reserve(User $patient, array $data, ?Appointment $existing = null): Appointment
    {
        $doctor = Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
        User::whereKey($patient->id)->lockForUpdate()->firstOrFail();
        $service = Service::findOrFail($data['service_id']);
        if (! $doctor->active || ! $service->active || ! $doctor->services()->whereKey($service->id)->exists()) {
            $this->fail('This doctor does not offer the selected service.');
        }
        $start = $this->time($data['starts_at']);
        $end = $start->addMinutes(30);
        $localStart = $start->setTimezone(config('clinic.timezone'));
        if ($start->lte(now()) || $start->second !== 0 || $start->microsecond !== 0 || ! in_array($localStart->minute, [0, 30])) {
            $this->fail('Choose a future half-hour slot.');
        }
        if (! Schedule::where('doctor_id', $doctor->id)->where('starts_at', '<=', $start)->where('ends_at', '>=', $end)->lockForUpdate()->first()) {
            $this->fail('This time is outside the doctor’s schedule.');
        }
        $overlap = Appointment::where('status', 'booked')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where(fn ($q) => $q->where('doctor_id', $doctor->id)->orWhere('patient_id', $patient->id));
        if ($existing) {
            $overlap->where('id', '!=', $existing->id);
        }
        if ($overlap->lockForUpdate()->first()) {
            $this->fail('The doctor or patient already has an appointment at this time.');
        }
        $appointment = $existing ?? new Appointment;
        if ($existing) {
            DB::table('reservations')->where('appointment_id', $appointment->id)->delete();
        }
        $appointment->fill(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $service->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => 'booked']);
        $appointment->save();
        DB::table('reservations')->insert(['appointment_id' => $appointment->id, 'doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'starts_at' => $start]);

        return $appointment->load('doctor.user', 'service', 'patient');
    }

    public function book(User $user, array $data): Appointment
    {
        Gate::forUser($user)->authorize('create', Appointment::class);

        return DB::transaction(fn () => $this->reserve($user, $data), 3);
    }

    public function reschedule(User $user, Appointment $appointment, array $data): Appointment
    {
        Gate::forUser($user)->authorize('update', $appointment);

        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status !== 'booked' || $appointment->starts_at->lte(now())) {
                $this->fail('Only future booked appointments can be rescheduled.');
            }
            if ((int) $data['doctor_id'] !== $appointment->doctor_id) {
                $this->fail('Rescheduling keeps the same doctor. Cancel and book again to change doctor.');
            }

            return $this->reserve($appointment->patient, $data, $appointment);
        }, 3);
    }

    public function transition(User $user, Appointment $appointment, string $status): Appointment
    {
        if (! in_array($status, ['cancelled', 'completed', 'no_show'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid appointment status.']);
        }
        Gate::forUser($user)->authorize($status === 'cancelled' ? 'update' : 'transition', $appointment);

        return DB::transaction(function () use ($appointment, $status) {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status !== 'booked') {
                $this->fail('A final appointment cannot change status.');
            }
            if ($status === 'cancelled' && $appointment->starts_at->lte(now())) {
                $this->fail('Only future appointments can be cancelled.');
            }
            if (in_array($status, ['completed', 'no_show']) && $appointment->ends_at->gt(now())) {
                $this->fail('Appointments can be finalized only after their end time.');
            }
            $appointment->update(['status' => $status]);
            DB::table('reservations')->where('appointment_id', $appointment->id)->delete();

            return $appointment->fresh()->load('doctor.user', 'service', 'patient');
        }, 3);
    }

    public function availability(Doctor $doctor, string $date): array
    {
        $from = CarbonImmutable::parse($date, config('clinic.timezone'))->startOfDay()->utc();
        $until = $from->setTimezone(config('clinic.timezone'))->addDay()->utc();
        $slots = [];
        if (! $doctor->active) {
            return [];
        }
        foreach ($doctor->schedules()->where('starts_at', '<', $until)->where('ends_at', '>', $from)->get() as $schedule) {
            for ($start = $schedule->starts_at->max($from); $start->addMinutes(30)->lte($schedule->ends_at) && $start->lt($until); $start = $start->addMinutes(30)) {
                if ($start->gt(now()) && ! Appointment::where('doctor_id', $doctor->id)->where('status', 'booked')->where('starts_at', '<', $start->addMinutes(30))->where('ends_at', '>', $start)->exists()) {
                    $slots[] = $start->toIso8601String();
                }
            }
        }

        return $slots;
    }

    public function schedule(array $data): Schedule
    {
        return DB::transaction(function () use ($data) {
            Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            $start = $this->time($data['starts_at']);
            $end = $this->time($data['ends_at']);
            if ($start->lte(now()) || $end->lte($start) || $end->diffInHours($start, true) > 12 || $start->second || $end->second || $start->microsecond || $end->microsecond || ! in_array($start->setTimezone(config('clinic.timezone'))->minute, [0, 30]) || ! in_array($end->setTimezone(config('clinic.timezone'))->minute, [0, 30])) {
                $this->fail('Use future half-hour boundaries, with a maximum 12-hour schedule.');
            }
            if (Schedule::where('doctor_id', $data['doctor_id'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                $this->fail('Schedules cannot overlap.');
            }

            return Schedule::create(['doctor_id' => $data['doctor_id'], 'starts_at' => $start, 'ends_at' => $end]);
        }, 3);
    }
}
