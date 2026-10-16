<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateSchoolUser extends Command
{
    protected $signature = 'school:user-add {email?} {role?}';

    protected $description = 'Create a school account and assign a role';

    public function handle(): int
    {
        $name = trim($this->ask('Name'));
        $email = strtolower(trim($this->argument('email') ?? $this->ask('Email')));
        $roles = Role::query()->orderBy('code')->get();

        if ($roles->isEmpty()) {
            $this->error('Roles do not exist yet. Run the database seeders first.');

            return self::FAILURE;
        }

        $roleCodes = $roles->pluck('code')->all();
        $roleCode = strtoupper(trim($this->argument('role') ?? $this->choice('Role', $roleCodes)));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'role' => $roleCode],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'role' => ['required', Rule::in($roleCodes)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $studentId = null;
        if ($roleCode === 'STUDENT') {
            $studentId = filter_var($this->ask('Student record ID'), FILTER_VALIDATE_INT);

            if (! $studentId || ! Student::query()->whereKey($studentId)->exists()) {
                $this->error('Enter the ID of an existing student record.');

                return self::FAILURE;
            }
        }

        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');

        if (! is_string($password) || strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role_id' => $roles->firstWhere('code', $roleCode)->id,
            'student_id' => $studentId,
        ]);

        $this->info("Created {$roleCode} account for {$email}.");

        return self::SUCCESS;
    }
}
