<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class MakeStaffAccount extends Command
{
    protected $signature = 'kf:make-admin';

    protected $description = 'Create a new K. Fabrics staff administrator with an interactive password prompt';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Administrator name'));
        $email = Str::lower(trim((string) $this->ask('Administrator email')));
        $password = $this->secret('Administrator password (at least 12 characters)');
        $confirmation = $this->secret('Confirm administrator password');

        $validation = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)]],
        );
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'role' => 'admin', 'password' => $password]);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
