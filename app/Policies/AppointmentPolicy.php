<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $u, Appointment $a): bool
    {
        return $u->role === 'admin' || $a->patient_id === $u->id || ($u->role === 'doctor' && $a->doctor->user_id === $u->id);
    }

    public function create(User $u): bool
    {
        return $u->role === 'patient';
    }

    public function update(User $u, Appointment $a): bool
    {
        return $u->role === 'admin' || ($u->role === 'patient' && $a->patient_id === $u->id);
    }

    public function transition(User $u, Appointment $a): bool
    {
        return $u->role === 'admin' || ($u->role === 'doctor' && $a->doctor->user_id === $u->id);
    }
}
