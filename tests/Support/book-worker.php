<?php

use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'clinicdesk_test') {
    throw new RuntimeException('Unsafe worker database.');
}
[$script,$patient,$doctor,$service,$start,$gate,$ready] = $argv;
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 10;
while (! file_exists($gate)) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
try {
    $a = app(BookingService::class)->book(User::findOrFail($patient), ['doctor_id' => $doctor, 'service_id' => $service, 'starts_at' => $start]);
    echo 'booked';
} catch (ValidationException $e) {
    echo 'unavailable';
}
