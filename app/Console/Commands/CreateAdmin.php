<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'clinicdesk:create-admin {email} {name}';

    protected $description = 'Create an administrator from a trusted operator console; password is entered privately';

    public function handle(): int
    {
        $password = $this->secret('Administrator password');
        $confirmation = $this->secret('Confirm password');
        $data = ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password, 'password_confirmation' => $confirmation];
        $validator = Validator::make($data, ['email' => 'required|email|max:254|unique:users', 'name' => 'required|string|max:120', 'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        $user = new User;
        $user->fill(collect($data)->only(['email', 'name', 'password'])->all());
        $user->role = 'admin';
        $user->save();
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
