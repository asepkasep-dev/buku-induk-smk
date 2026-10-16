<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassAssignment;
use App\Models\Competency;
use App\Models\OperatorRombelScope;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function schoolUsersAdmin(): User
{
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);

    return User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);
}

test('admin can create a user account without recording its password in the audit log', function () {
    $admin = schoolUsersAdmin();

    Livewire::actingAs($admin)
        ->test('pages.admin.users')
        ->set('name', 'Operator Satu')
        ->set('email', 'operator@sekolah.local')
        ->set('roleCode', 'OPERATOR')
        ->set('password', 'KataSandiSekolah!2099')
        ->set('passwordConfirmation', 'KataSandiSekolah!2099')
        ->call('saveUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'operator@sekolah.local')->firstOrFail();
    expect($user->role->code)->toBe('OPERATOR')
        ->and(Hash::check('KataSandiSekolah!2099', $user->password))->toBeTrue();
    $audit = AuditLog::query()->where('action', 'CREATE_SCHOOL_USER')->firstOrFail();
    expect($audit->after_data)->toBe(['role' => 'OPERATOR'])
        ->and(json_encode($audit->after_data))->not->toContain('KataSandiSekolah');
});

test('admin can scope an operator and assign a homeroom teacher', function () {
    $admin = schoolUsersAdmin();
    $operator = User::factory()->create([
        'role_id' => Role::query()->where('code', 'OPERATOR')->firstOrFail()->id,
    ]);
    $teacher = User::factory()->create([
        'role_id' => Role::query()->where('code', 'WALI_KELAS')->firstOrFail()->id,
    ]);
    $year = AcademicYear::query()->create([
        'name' => '2097/2098', 'start_date' => '2097-07-01', 'end_date' => '2098-06-30', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::query()->create([
        'code' => 'USER-TEST', 'name' => 'Konsentrasi Uji', 'short_name' => 'UJI', 'is_active' => true,
    ]);
    $rombel = Rombel::query()->create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X UJI 1', 'is_active' => true,
    ]);

    Livewire::actingAs($admin)->test('pages.admin.users')
        ->set('operatorUserId', $operator->id)
        ->set('operatorRombelId', $rombel->id)
        ->call('assignOperatorRombel')
        ->assertHasNoErrors()
        ->set('homeroomUserId', $teacher->id)
        ->set('homeroomYearId', $year->id)
        ->set('homeroomRombelId', $rombel->id)
        ->call('assignHomeroom')
        ->assertHasNoErrors();

    expect(OperatorRombelScope::query()->where('user_id', $operator->id)->where('rombel_id', $rombel->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(ClassAssignment::query()->where('user_id', $teacher->id)->where('rombel_id', $rombel->id)->where('status', 'ACTIVE')->exists())->toBeTrue()
        ->and(AuditLog::query()->whereIn('action', ['ASSIGN_OPERATOR_ROMBEL', 'ASSIGN_HOMEROOM'])->count())->toBe(2);
});

test('operator cannot open account management', function () {
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);
    $operator = User::factory()->create([
        'role_id' => Role::query()->where('code', 'OPERATOR')->firstOrFail()->id,
    ]);

    $this->actingAs($operator)->get(route('admin.users'))->assertForbidden();
});

test('server command resets a password, clears remembered sessions, and stores no secret in the audit log', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $user = User::factory()->create([
        'role_id' => Role::query()->where('code', 'OPERATOR')->firstOrFail()->id,
        'password' => 'OldPassword!2099',
    ]);
    DB::table('sessions')->insert([
        'id' => 'reset-test-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp,
    ]);

    $this->artisan('school:user-password-reset', ['email' => $user->email])
        ->expectsQuestion('New password (at least 12 characters)', 'NewPassword!2099')
        ->expectsQuestion('Confirm new password', 'NewPassword!2099')
        ->assertExitCode(0);

    expect(Hash::check('NewPassword!2099', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    $audit = AuditLog::query()->where('action', 'RESET_SCHOOL_USER_PASSWORD')->sole();
    expect($audit->after_data)->toBe(['changed' => true, 'source' => 'server_console'])
        ->and(json_encode($audit->after_data))->not->toContain('NewPassword');
});
