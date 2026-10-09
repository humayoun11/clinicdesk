<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $r)
    {
        $query = Appointment::visibleTo($r->user());
        $totals = ['Appointments' => (clone $query)->count(), 'Upcoming' => (clone $query)->where('status', 'booked')->where('starts_at', '>', now())->count(), 'Completed' => (clone $query)->where('status', 'completed')->count()];
        if ($r->user()->role === 'admin') {
            $totals += ['Active doctors' => Doctor::where('active', true)->count(), 'Active services' => Service::where('active', true)->count()];
        }
        $upcoming = (clone $query)->with('doctor.user', 'patient', 'service')->where('status', 'booked')->where('starts_at', '>', now())->orderBy('starts_at')->limit(6)->get();
        $schedules = $r->user()->role === 'doctor' ? Schedule::whereHas('doctor', fn ($q) => $q->where('user_id', $r->user()->id))->where('ends_at', '>', now())->orderBy('starts_at')->paginate(10) : null;

        return view('dashboard', compact('totals', 'upcoming', 'schedules'));
    }
}
