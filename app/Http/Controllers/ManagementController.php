<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\ManagementRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagementController extends Controller
{
    private function model(string $kind): string
    {
        return match ($kind) {
            'doctors' => Doctor::class,'services' => Service::class,'schedules' => Schedule::class,default => abort(404)
        };
    }

    public function index(ListRequest $r, string $kind)
    {
        $model = $this->model($kind);
        $q = $model::query();
        if ($kind === 'doctors') {
            $q->with('user', 'services')->when($r->q, fn ($q, $s) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")));
        }
        if ($kind === 'services') {
            $q->when($r->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"));
        }
        if ($kind === 'schedules') {
            $q->with('doctor.user');
        }

        return view('admin.index', ['kind' => $kind, 'items' => $q->latest('id')->paginate(12)->withQueryString()]);
    }

    public function form(string $kind, ?int $id = null)
    {
        $model = $this->model($kind);
        abort_if($kind === 'schedules' && $id, 404);

        return view('admin.form', ['kind' => $kind, 'item' => $id ? $model::findOrFail($id) : null, 'services' => Service::all(), 'doctors' => Doctor::with('user')->where('active', true)->get()]);
    }

    public function save(ManagementRequest $r, BookingService $b, string $kind, ?int $id = null)
    {
        $data = $r->validated();
        if ($kind === 'schedules') {
            abort_if($id, 404);
            $b->schedule($data);
        } else {
            DB::transaction(function () use ($kind, $id, $data) {
                if ($kind === 'services') {
                    $s = $id ? Service::findOrFail($id) : new Service;
                    $s->fill($data)->save();
                } else {
                    $doctor = $id ? Doctor::whereKey($id)->lockForUpdate()->firstOrFail() : new Doctor;
                    $user = $id ? $doctor->user : new User;
                    $user->fill(collect($data)->only(['name', 'email'])->all());
                    if (! empty($data['password'])) {
                        $user->password = $data['password'];
                        if ($id) {
                            $user->tokens()->delete();
                        }
                    }
                    $user->role = 'doctor';
                    $user->save();
                    $doctor->fill(collect($data)->only(['specialty', 'bio', 'active'])->all());
                    $doctor->user_id = $user->id;
                    $doctor->save();
                    $doctor->services()->sync($data['services']);
                }
            });
        }

        return redirect()->route('admin.index', $kind)->with('success', 'Saved successfully.');
    }

    public function destroy(string $kind, int $id)
    {
        abort_unless($kind === 'schedules', 404);
        DB::transaction(function () use ($id) {
            $schedule = Schedule::findOrFail($id);
            Doctor::whereKey($schedule->doctor_id)->lockForUpdate()->firstOrFail();
            if (Appointment::where('doctor_id', $schedule->doctor_id)->where('status', 'booked')->where('starts_at', '<', $schedule->ends_at)->where('ends_at', '>', $schedule->starts_at)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['schedule' => 'Cancel or reschedule affected appointments before removing this schedule.']);
            }
            $schedule->delete();
        });

        return back()->with('success', 'Schedule removed.');
    }
}
