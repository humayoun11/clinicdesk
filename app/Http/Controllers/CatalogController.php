<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\ServiceResource;
use App\Models\Doctor;
use App\Models\Service;
use App\Services\BookingService;

class CatalogController extends Controller
{
    public function doctors(ListRequest $r)
    {
        $doctors = Doctor::with('user', 'services')->where('active', true)->when($r->q, fn ($q, $s) => $q->where(fn ($q) => $q->where('specialty', 'like', "%{$s}%")->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%"))))->paginate($r->integer('per_page', 12))->withQueryString();

        return $r->is('api/*') ? DoctorResource::collection($doctors) : view('catalog.doctors', compact('doctors'));
    }

    public function services(ListRequest $r)
    {
        $services = Service::where('active', true)->when($r->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))->paginate($r->integer('per_page', 12))->withQueryString();

        return $r->is('api/*') ? ServiceResource::collection($services) : view('catalog.services', compact('services'));
    }

    public function availability(ListRequest $r, Doctor $doctor, BookingService $booking)
    {
        abort_unless($doctor->active, 404);
        $r->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:'.now(config('clinic.timezone'))->format('Y-m-d').'|before_or_equal:'.now(config('clinic.timezone'))->addDays(90)->format('Y-m-d')]);

        return response()->json(['data' => ['doctor_id' => $doctor->id, 'date' => $r->date, 'timezone' => config('clinic.timezone'), 'slots' => $booking->availability($doctor, $r->date)]]);
    }
}
