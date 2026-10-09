<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('patient')->index();
        });
        Schema::create('doctors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->string('specialty');
            $t->text('bio');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('doctor_service', function (Blueprint $t) {
            $t->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->primary(['doctor_id', 'service_id']);
        });
        Schema::create('schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->timestamps();
            $t->index(['doctor_id', 'starts_at', 'ends_at']);
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('status')->default('booked');
            $t->timestamps();
            $t->index(['doctor_id', 'starts_at', 'status']);
            $t->index(['patient_id', 'starts_at']);
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('doctor_id')->constrained();
            $t->foreignId('patient_id')->constrained('users');
            $t->dateTime('starts_at');
            $t->unique(['doctor_id', 'starts_at']);
            $t->unique(['patient_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        foreach (['reservations', 'appointments', 'schedules', 'doctor_service', 'services', 'doctors'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
