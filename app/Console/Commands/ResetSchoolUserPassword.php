<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetSchoolUserPassword extends Command
{
    protected $signature = 'school:user-password-reset {email?}';

    protected $description = 'Reset a school account password from the server console';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email') ?? $this->ask('Account email')));
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->error('Account not found.');

            return self::FAILURE;
        }

        $password = $this->secret('New password (at least 12 characters)');
        $confirmation = $this->secret('Confirm new password');
        if (! is_string($password) || strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditLog::query()->create([
                'action' => 'RESET_SCHOOL_USER_PASSWORD',
                'resource_type' => User::class,
                'resource_id' => $user->id,
                'after_data' => ['changed' => true, 'source' => 'server_console'],
            ]);
        });

        $this->info('Password reset successfully.');

        return self::SUCCESS;
    }
}
