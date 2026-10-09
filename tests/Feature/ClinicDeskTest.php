<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ClinicDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_reschedule_rolls_back_and_preserves_the_original_reservation(): void
    {
        [$patient, $doctor, $service, $data] = $this->fixture();
        $appointment = app(BookingService::class)->book($patient, $data);
        $conflict = $data;
        $conflict['starts_at'] = now()->addDay()->setTime(6, 0)->toIso8601String();
        app(BookingService::class)->book(User::factory()->create(), $conflict);

        $this->actingAs($patient)->patch('/appointments/'.$appointment->id.'/reschedule', $conflict)
            ->assertSessionHasErrors('starts_at');
        $this->assertSame('05:00', $appointment->fresh()->starts_at->format('H:i'));
        $this->assertDatabaseHas('reservations', ['appointment_id' => $appointment->id, 'starts_at' => $appointment->starts_at]);

        $conflict['doctor_id'] = Doctor::factory()->create()->id;
        $this->patch('/appointments/'.$appointment->id.'/reschedule', $conflict)->assertSessionHasErrors('starts_at');
    }

    public function test_admin_can_create_update_and_deactivate_doctors_and_services(): void
    {
        $admin = $this->role('admin');
        $service = Service::factory()->create();
        $data = ['name' => 'Dr. Avery Demo', 'email' => 'avery@clinicdesk.example', 'password' => 'DemoPassword123', 'specialty' => 'Family Medicine', 'bio' => 'Fictional clinician.', 'active' => 1, 'services' => [$service->id]];
        $this->actingAs($admin)->post('/admin/doctors', $data)->assertRedirect('/admin/doctors');
        $doctor = Doctor::whereHas('user', fn ($q) => $q->where('email', $data['email']))->firstOrFail();
        $this->assertSame('doctor', $doctor->user->role);
        $this->assertTrue($doctor->services()->whereKey($service->id)->exists());
        $data['active'] = 0;
        $data['password'] = '';
        $data['name'] = 'Dr. Avery Updated';
        $this->put('/admin/doctors/'.$doctor->id, $data)->assertRedirect('/admin/doctors');
        $this->assertFalse($doctor->fresh()->active);
        $this->assertTrue(Hash::check('DemoPassword123', $doctor->user->fresh()->password));
        $this->get('/doctors')->assertDontSee('Dr. Avery Updated');
        $this->put('/admin/services/'.$service->id, ['name' => 'Updated service', 'description' => 'Fictional', 'active' => 0])->assertRedirect('/admin/services');
        $this->assertFalse($service->fresh()->active);
        $this->get('/services')->assertDontSee('Updated service');
    }

    public function test_api_registration_and_doctor_authorization_match_the_website(): void
    {
        $registration = ['name' => 'API Patient', 'email' => 'api-patient@clinicdesk.example', 'password' => 'DemoPassword123', 'password_confirmation' => 'DemoPassword123'];
        $this->postJson('/api/v1/register', $registration + ['role' => 'doctor'])->assertUnprocessable();
        $this->postJson('/api/v1/register', $registration)->assertCreated()->assertJsonPath('data.role', 'patient');
        [$patient, $doctor, $service, $data] = $this->fixture();
        $appointment = app(BookingService::class)->book($patient, $data);
        $this->withToken($this->token($doctor->user));
        $this->getJson('/api/v1/appointments/'.$appointment->id)->assertOk();
        $this->postJson('/api/v1/appointments', $data)->assertForbidden();
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel')->assertForbidden();
        $this->patchJson('/api/v1/appointments/'.$appointment->id.'/reschedule', $data)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token(Doctor::factory()->create()->user))->getJson('/api/v1/appointments/'.$appointment->id)->assertForbidden();
    }

    private function fixture(): array
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()->create();
        $service = Service::factory()->create();
        $doctor->services()->attach($service);
        $start = now()->addDay()->setTime(5, 0);
        Schedule::create(['doctor_id' => $doctor->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHours(4)]);

        return [$patient, $doctor, $service, ['doctor_id' => $doctor->id, 'service_id' => $service->id, 'starts_at' => $start->toIso8601String()]];
    }

    private function role(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test', ['*'], now()->addHour())->plainTextToken;
    }

    public function test_registration_is_patient_and_role_escalation_is_rejected(): void
    {
        $data = ['name' => 'Demo Patient', 'email' => 'new@clinicdesk.example', 'password' => 'DemoPassword123', 'password_confirmation' => 'DemoPassword123'];
        $this->post('/register', $data + ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->post('/register', $data)->assertRedirect('/dashboard');
        $u = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('patient', $u->role);
        $this->assertTrue(Hash::check($data['password'], $u->password));
        $this->assertAuthenticatedAs($u);
    }

    public function test_login_logout_and_invalid_credentials(): void
    {
        $u = User::factory()->create(['password' => 'DemoPassword123']);
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $u->email, 'password' => 'DemoPassword123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_password_reset_notification_and_token_revocation(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $u->createToken('old');
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('success');
        Notification::assertSentTo($u, ResetPassword::class);
        $token = Password::createToken($u);
        $this->get('/reset-password/'.$token.'?email='.urlencode($u->email))->assertOk();
        $this->post('/reset-password', ['email' => $u->email, 'token' => $token, 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('ChangedPassword123', $u->fresh()->password));
        $this->assertSame(0, $u->tokens()->count());
        $this->post('/reset-password', ['email' => $u->email, 'token' => $token, 'password' => 'AnotherPassword123', 'password_confirmation' => 'AnotherPassword123'])->assertSessionHasErrors();
    }

    public function test_profile_cannot_change_role_and_password_requires_current_password(): void
    {
        $u = User::factory()->create(['password' => 'DemoPassword123']);
        $this->actingAs($u)->patch('/profile', ['name' => 'Changed', 'email' => $u->email, 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->patch('/profile', ['name' => 'Changed', 'email' => $u->email])->assertSessionHas('success');
        $this->assertSame('Changed', $u->fresh()->name);
        $u->createToken('old');
        $this->put('/profile/password', ['current_password' => 'bad', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'DemoPassword123', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertSessionHas('success');
        $this->assertSame(0, $u->tokens()->count());
    }

    public function test_booking_rescheduling_and_cancellation_release_slots(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $this->actingAs($p)->post('/appointments', $data)->assertRedirect();
        $a = Appointment::firstOrFail();
        $this->assertDatabaseHas('reservations', ['appointment_id' => $a->id]);
        $next = $data;
        $next['starts_at'] = now()->addDay()->setTime(6, 0)->toIso8601String();
        $this->patch('/appointments/'.$a->id.'/reschedule', $next)->assertRedirect();
        $this->assertSame('06:00', $a->fresh()->starts_at->format('H:i'));
        $this->post('/appointments/'.$a->id.'/cancel')->assertRedirect();
        $this->assertDatabaseMissing('reservations', ['appointment_id' => $a->id]);
        $this->assertSame('cancelled', $a->fresh()->status);
        $this->post('/appointments', $next)->assertRedirect();
        $this->assertSame(2, Appointment::count());
    }

    public function test_past_dates_off_grid_and_outside_schedule_are_rejected(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $this->actingAs($p);
        foreach ([now()->subDay()->setTime(5, 0), now()->addDay()->setTime(5, 15), now()->addDay()->setTime(12, 0)] as $time) {
            $data['starts_at'] = $time->toIso8601String();
            $this->post('/appointments', $data)->assertSessionHasErrors('starts_at');
        }
        $this->assertSame(0, Appointment::count());
    }

    public function test_double_booking_and_patient_overlap_are_rejected(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        app(BookingService::class)->book($p, $data);
        $other = User::factory()->create();
        $this->actingAs($other)->post('/appointments', $data)->assertSessionHasErrors('starts_at');
        $second = Doctor::factory()->create();
        $second->services()->attach($s);
        Schedule::create(['doctor_id' => $second->id, 'starts_at' => now()->addDay()->setTime(5, 0), 'ends_at' => now()->addDay()->setTime(9, 0)]);
        $data['doctor_id'] = $second->id;
        $this->actingAs($p)->post('/appointments', $data)->assertSessionHasErrors('starts_at');
        $this->assertSame(1, Appointment::count());
    }

    public function test_unique_reservation_constraint_is_database_enforced(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $a = app(BookingService::class)->book($p, $data);
        $copy = Appointment::create($a->only(['patient_id', 'doctor_id', 'service_id', 'starts_at', 'ends_at', 'status']));
        $this->expectException(QueryException::class);
        DB::table('reservations')->insert(['appointment_id' => $copy->id, 'doctor_id' => $d->id, 'patient_id' => $p->id, 'starts_at' => $a->starts_at]);
    }

    public function test_cross_user_access_is_forbidden_on_web_and_api(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $a = app(BookingService::class)->book($p, $data);
        $other = User::factory()->create();
        $this->actingAs($other)->get('/appointments/'.$a->id)->assertForbidden();
        $this->get('/appointments/'.$a->id.'/edit')->assertForbidden();
        $this->post('/appointments/'.$a->id.'/cancel')->assertForbidden();
        $this->patch('/appointments/'.$a->id.'/reschedule', $data)->assertForbidden();
        $this->get('/appointments')->assertDontSee($d->user->name);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($other))->getJson('/api/v1/appointments/'.$a->id)->assertForbidden();
        $this->getJson('/api/v1/appointments')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/appointments/'.$a->id.'/cancel')->assertForbidden();
    }

    public function test_doctor_permissions_are_limited_to_assigned_appointments(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $a = app(BookingService::class)->book($p, $data);
        $this->actingAs($d->user)->get('/appointments/'.$a->id)->assertOk();
        $this->post('/appointments/'.$a->id.'/cancel')->assertForbidden();
        $this->get('/appointments/create')->assertForbidden();
        $other = Doctor::factory()->create();
        $this->actingAs($other->user)->get('/appointments/'.$a->id)->assertForbidden();
        $this->get('/admin/doctors')->assertForbidden();
    }

    public function test_status_transitions_are_final_and_require_end_time(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $a = app(BookingService::class)->book($p, $data);
        $this->actingAs($d->user)->patch('/appointments/'.$a->id.'/status', ['status' => 'completed'])->assertSessionHasErrors();
        $this->actingAs($p)->patch('/appointments/'.$a->id.'/status', ['status' => 'completed'])->assertForbidden();
        $this->travel(2)->days();
        $this->actingAs($p)->post('/appointments/'.$a->id.'/cancel')->assertSessionHasErrors();
        $this->actingAs($d->user)->patch('/appointments/'.$a->id.'/status', ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $a->fresh()->status);
        $this->patch('/appointments/'.$a->id.'/status', ['status' => 'no_show'])->assertSessionHasErrors();
        $this->travelBack();
    }

    public function test_admin_management_schedule_overlap_and_removal_rules(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $admin = $this->role('admin');
        $this->actingAs($p)->post('/admin/services', ['name' => 'Test', 'description' => 'Test', 'active' => 1])->assertForbidden();
        $this->actingAs($admin)->post('/admin/services', ['name' => 'New service', 'description' => 'Fictional service', 'active' => 1])->assertRedirect('/admin/services');
        $scheduleData = ['doctor_id' => $d->id, 'starts_at' => $data['starts_at'], 'ends_at' => now()->addDay()->setTime(6, 0)->toIso8601String()];
        $this->post('/admin/schedules', $scheduleData)->assertSessionHasErrors();
        $scheduleData['starts_at'] = now()->addDays(2)->setTime(5, 0)->toIso8601String();
        $scheduleData['ends_at'] = now()->addDays(2)->setTime(6, 0)->toIso8601String();
        $this->post('/admin/schedules', $scheduleData)->assertRedirect('/admin/schedules');
        $a = app(BookingService::class)->book($p, $data);
        $schedule = Schedule::where('doctor_id', $d->id)->first();
        $this->delete('/admin/schedules/'.$schedule->id)->assertSessionHasErrors();
        $this->post('/appointments/'.$a->id.'/cancel')->assertRedirect();
        $this->delete('/admin/schedules/'.$schedule->id)->assertRedirect();
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_inactive_and_unoffered_services_cannot_be_booked(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $this->actingAs($p);
        $s->update(['active' => false]);
        $this->post('/appointments', $data)->assertSessionHasErrors();
        $s->update(['active' => true]);
        $d->services()->detach();
        $this->post('/appointments', $data)->assertSessionHasErrors();
        $d->services()->attach($s);
        $d->update(['active' => false]);
        $this->post('/appointments', $data)->assertSessionHasErrors();
    }

    public function test_api_authentication_validation_expiration_and_revocation(): void
    {
        $u = User::factory()->create(['password' => 'DemoPassword123']);
        $this->getJson('/api/v1/user')->assertUnauthorized()->assertJsonStructure(['message', 'errors']);
        $this->postJson('/api/v1/login', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        $login = $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'DemoPassword123'])->assertOk()->assertJsonPath('data.token_type', 'Bearer');
        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.id', $u->id);
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $expired = $u->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($expired)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_api_booking_reschedule_cancel_and_bounded_pagination(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $this->withToken($this->token($p));
        $this->postJson('/api/v1/appointments', $data + ['patient_id' => 99])->assertUnprocessable();
        $result = $this->postJson('/api/v1/appointments', $data)->assertCreated()->assertJsonPath('data.status', 'booked');
        $id = $result->json('data.id');
        $this->postJson('/api/v1/appointments', $data)->assertUnprocessable();
        $data['starts_at'] = now()->addDay()->setTime(6, 0)->toIso8601String();
        $this->patchJson('/api/v1/appointments/'.$id.'/reschedule', $data)->assertOk();
        $this->postJson('/api/v1/appointments/'.$id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/v1/appointments/'.$id.'/cancel')->assertUnprocessable();
        $this->getJson('/api/v1/doctors?per_page=999')->assertUnprocessable();
        $this->getJson('/api/v1/appointments?per_page=1')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['message', 'errors']);
    }

    public function test_availability_is_real_and_removes_booked_slots(): void
    {
        [$p,$d,$s,$data] = $this->fixture();
        $date = now(config('clinic.timezone'))->addDay()->format('Y-m-d');
        $this->getJson('/api/v1/doctors/'.$d->id.'/availability?date='.$date)->assertOk()->assertJsonCount(8, 'data.slots');
        app(BookingService::class)->book($p, $data);
        $this->getJson('/api/v1/doctors/'.$d->id.'/availability?date='.$date)->assertOk()->assertJsonCount(7, 'data.slots');
        $this->getJson('/api/v1/doctors/'.$d->id.'/availability?date=bad')->assertUnprocessable();
    }

    public function test_login_registration_and_password_reset_are_rate_limited(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'wrong'])->assertStatus(429);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'missing@clinicdesk.example'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'missing@clinicdesk.example'])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/register', [])->assertStatus(429);
    }

    public function test_csrf_is_enforced_when_test_bypass_is_disabled(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'a@clinicdesk.example', 'password' => 'anything'])->assertStatus(419);
        $this->post('/register', [])->assertStatus(419);
    }

    public function test_pages_render_for_every_role_and_seed_is_repeatable(): void
    {
        $this->seed(DemoSeeder::class);
        $count = User::count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($count, User::count());
        foreach (['/', '/doctors', '/services', '/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['patient', 'doctor', 'admin'] as $role) {
            $u = User::where('role', $role)->firstOrFail();
            $this->app['session']->flush();
            $this->actingAs($u);
            foreach (['/dashboard', '/profile', '/appointments'] as $path) {
                $this->get($path)->assertOk();
            }
            $a = Appointment::visibleTo($u)->where('status', 'booked')->first();
            if ($a) {
                $this->get('/appointments/'.$a->id)->assertOk();
            }
            if ($role === 'patient') {
                $this->get('/appointments/create')->assertOk();
                $this->get('/appointments/'.$a->id.'/edit')->assertOk();
            }
            if ($role === 'admin') {
                foreach (['doctors', 'services', 'schedules'] as $kind) {
                    $this->get('/admin/'.$kind)->assertOk();
                    $this->get('/admin/'.$kind.'/create')->assertOk();
                }
                foreach (['doctors' => Doctor::first()->id, 'services' => Service::first()->id] as $kind => $id) {
                    $this->get('/admin/'.$kind.'/'.$id.'/edit')->assertOk();
                }
            }
        }
    }

    public function test_security_headers_and_api_catalog(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy');
        $this->getJson('/api/v1/services')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }
}
