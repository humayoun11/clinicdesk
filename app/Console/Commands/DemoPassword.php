<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class DemoPassword extends Command
{
    protected $signature = 'clinicdesk:demo-password {--random : Generate and display a fresh demo password}';

    protected $description = 'Set passwords for fictional seeded demo accounts in clinicdesk_demo only';

    public function handle(): int
    {
        if (app()->isProduction() || DB::connection()->getDatabaseName() !== 'clinicdesk_demo') {
            $this->error('This command only operates on local clinicdesk_demo.');

            return self::FAILURE;
        }
        $password = $this->option('random') ? Str::password(18) : $this->secret('New demo password (10+ characters, upper/lowercase and a number)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(10)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $emails = ['admin@clinicdesk.example', 'doctor@clinicdesk.example', 'patient@clinicdesk.example', 'taylor@clinicdesk.example', 'oliver@clinicdesk.example', 'sofia@clinicdesk.example'];
        $users = User::whereIn('email', $emails)->get();
        if ($users->isEmpty()) {
            $this->error('Run php artisan db:seed first.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($users, $password) {
            foreach ($users as $u) {
                $u->password = $password;
                $u->remember_token = Str::random(60);
                $u->save();
                $u->tokens()->delete();
            }
        });
        $this->info('Updated fictional demo accounts; tokens revoked.');
        if ($this->option('random')) {
            $this->line('Demo password: '.$password);
        }

return self::SUCCESS;
    }
}
