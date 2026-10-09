<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    public function index(ListRequest $r)
    {
        $appointments = Appointment::visibleTo($r->user())->with('doctor.user', 'doctor.services', 'service', 'patient')->when($r->status, fn ($q, $s) => $q->where('status', $s))->when($r->q, fn ($q, $s) => $q->where(fn ($q) => $q->whereHas('doctor.user', fn ($q) => $q->where('name', 'like', "%{$s}%"))->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"))->orWhereHas('service', fn ($q) => $q->where('name', 'like', "%{$s}%"))))->orderByDesc('starts_at')->paginate($r->integer('per_page', 12))->withQueryString();

        return $r->is('api/*') ? AppointmentResource::collection($appointments) : view('appointments.index', compact('appointments'));
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Appointment::class);

        return view('appointments.form', ['doctors' => Doctor::with('user', 'services')->where('active', true)->get(), 'appointment' => null]);
    }

    public function show(Request $r, Appointment $appointment)
    {
        Gate::authorize('view', $appointment);
        $appointment->load('doctor.user', 'doctor.services', 'service', 'patient');

        return $r->is('api/*') ? new AppointmentResource($appointment) : view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        Gate::authorize('update', $appointment);
        abort_unless($appointment->status === 'booked' && $appointment->starts_at->gt(now()), 422);

        return view('appointments.form', ['appointment' => $appointment, 'doctors' => Doctor::with('user', 'services')->whereKey($appointment->doctor_id)->get()]);
    }

    public function store(BookingRequest $r, BookingService $b)
    {
        $a = $b->book($r->user(), $r->validated());

        return $r->is('api/*') ? (new AppointmentResource($a))->response()->setStatusCode(201) : redirect()->route('appointments.show', $a)->with('success', 'Appointment booked.');
    }

    public function reschedule(BookingRequest $r, Appointment $appointment, BookingService $b)
    {
        $a = $b->reschedule($r->user(), $appointment, $r->validated());

        return $r->is('api/*') ? new AppointmentResource($a) : redirect()->route('appointments.show', $a)->with('success', 'Appointment rescheduled.');
    }

    public function cancel(Request $r, Appointment $appointment, BookingService $b)
    {
        $a = $b->transition($r->user(), $appointment, 'cancelled');

        return $r->is('api/*') ? new AppointmentResource($a) : back()->with('success', 'Appointment cancelled.');
    }

    public function status(StatusRequest $r, Appointment $appointment, BookingService $b)
    {
        $b->transition($r->user(),$appointment,$r->status);

        return back()->with('success','Appointment status updated.');
    }
}
