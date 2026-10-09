<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_valid_role CHECK (role IN ('admin','doctor','patient'))");
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_valid_status CHECK (status IN ('booked','cancelled','completed','no_show'))");
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_duration CHECK (TIMESTAMPDIFF(SECOND, starts_at, ends_at) = 1800)');
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_positive_length CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        foreach (['users' => ['users_valid_role'], 'appointments' => ['appointments_valid_status', 'appointments_duration'], 'schedules' => ['schedules_positive_length']] as $table => $constraints) {
            foreach ($constraints as $constraint) {
                $verb = str_contains(strtolower(DB::selectOne('SELECT VERSION() AS version')->version), 'mariadb') ? 'DROP CONSTRAINT' : 'DROP CHECK';
                DB::statement("ALTER TABLE {$table} {$verb} {$constraint}");
            }
        }
    }
};
