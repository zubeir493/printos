<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateProductionAdmin extends Command
{
    protected $signature = 'printos:create-admin';

    protected $description = 'Create an administrator with a password entered at the terminal';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command in an interactive terminal.');

            return self::FAILURE;
        }

        $identity = Validator::make([
            'name' => trim((string) $this->ask('Name')),
            'email' => strtolower(trim((string) $this->ask('Email'))),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $password = Validator::make([
            'password' => $this->secret('Password', false),
            'password_confirmation' => $this->secret('Confirm password', false),
        ], [
            'password' => [
                'bail', 'required', 'string', 'min:14', 'confirmed',
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('The password must be at most 72 bytes.');
                    }
                },
            ],
        ]);

        $errors = [...$identity->errors()->all(), ...$password->errors()->all()];
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            ...$identity->validated(),
            'password' => Hash::make($password->validated()['password']),
            'role' => UserRole::Admin,
        ]);

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
