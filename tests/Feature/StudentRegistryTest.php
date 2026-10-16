<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can create a student with an audit record', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->firstOrFail()->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages.students.create')
        ->set('nis', 'SMK-001')
        ->set('fullName', 'Siswa Pertama')
        ->set('gender', 'P')
        ->set('entryYear', '2026')
        ->set('status', 'AKTIF')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();
    expect($student->nis)->toBe('SMK-001')
        ->and($student->full_name)->toBe('Siswa Pertama')
        ->and($student->gender)->toBe('P');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'CREATE_STUDENT',
        'resource_type' => Student::class,
        'resource_id' => $student->id,
    ]);
});

test('admin can update a student and record changed fields', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->firstOrFail()->id,
    ]);
    $student = Student::create([
        'nis' => 'SMK-002', 'full_name' => 'Nama Sebelum', 'gender' => 'L',
        'entry_year' => 2026, 'status' => 'AKTIF',
    ]);

    Livewire::actingAs($admin)
        ->test('pages.students.edit', ['student' => $student])
        ->set('fullName', 'Nama Sesudah')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('students', ['id' => $student->id, 'full_name' => 'Nama Sesudah']);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'UPDATE_STUDENT',
        'resource_type' => Student::class,
        'resource_id' => $student->id,
    ]);
    expect(AuditLog::sole()->after_data['changed_fields'])->toContain('full_name');
});

test('operator creates a student directly in an assigned rombel', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $operator = User::factory()->create([
        'role_id' => Role::where('code', 'OPERATOR')->firstOrFail()->id,
    ]);
    $year = AcademicYear::query()->create([
        'name' => '2099/2100', 'start_date' => '2099-07-01', 'end_date' => '2100-06-30', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::query()->create([
        'code' => 'REG-MPLB', 'name' => 'Manajemen Perkantoran', 'short_name' => 'MPLB', 'is_active' => true,
    ]);
    $rombel = Rombel::query()->create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X MPLB 1', 'is_active' => true,
    ]);
    $operator->operatorRombelScopes()->create(['rombel_id' => $rombel->id, 'is_active' => true]);

    Livewire::actingAs($operator)
        ->test('pages.students.create')
        ->set('nis', 'OP-001')
        ->set('fullName', 'Siswa Operator')
        ->set('gender', 'L')
        ->set('entryYear', '2099')
        ->set('status', 'AKTIF')
        ->set('rombelId', $rombel->id)
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'OP-001')->firstOrFail();
    expect(Enrollment::query()->where('student_id', $student->id)->where('rombel_id', $rombel->id)->where('status', 'ACTIVE')->exists())->toBeTrue()
        ->and($operator->accessibleStudentsAsOperator()->whereKey($student->id)->exists())->toBeTrue();
});
