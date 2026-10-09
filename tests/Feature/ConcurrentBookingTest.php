<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentBookingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_processes_competing_for_one_slot_create_exactly_one_booking(): void
    {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $d = Doctor::factory()->create();
        $s = Service::factory()->create();
        $d->services()->attach($s);
        $start = now()->addDay()->setTime(5, 0);
        Schedule::create(['doctor_id' => $d->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $prefix = $dir.'/race-'.bin2hex(random_bytes(8));
        $gate = $prefix.'-gate';
        $ready1 = $prefix.'-1';
        $ready2 = $prefix.'-2';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'clinicdesk_test', 'CACHE_STORE' => 'array'];
        $workers = [];
        foreach ([[$p1, $ready1], [$p2, $ready2]] as [$p,$ready]) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/book-worker.php'), (string) $p->id, (string) $d->id, (string) $s->id, $start->toIso8601String(), $gate, $ready], base_path(), $env);
            $worker->setTimeout(20);
            $worker->start();
            $workers[] = $worker;
        }
        try {
            $deadline = microtime(true) + 10;
            while (! file_exists($ready1) || ! file_exists($ready2)) {
                if (microtime(true) > $deadline) {
                    $this->fail('Booking workers did not reach the barrier.');
                }
                usleep(10000);
            }
            file_put_contents($gate, 'go');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = trim($worker->getOutput());
            }
            sort($results);
            $this->assertSame(['booked', 'unavailable'], $results);
            $this->assertSame(1, Appointment::where('status', 'booked')->count());
            $this->assertDatabaseCount('reservations', 1);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ([$gate, $ready1, $ready2] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
