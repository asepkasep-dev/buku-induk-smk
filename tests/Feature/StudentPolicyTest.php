<?php

use App\Models\AcademicYear;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\CompetencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operator can update only students in an active assigned class', function () {
    $this->seed([CompetencySeeder::class, RoleSeeder::class, PermissionSeeder::class]);

    $year = AcademicYear::create([
        'name' => '2099/2100', 'start_date' => '2099-07-01',
        'end_date' => '2100-06-30', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::firstOrFail();
    $assignedClass = Rombel::create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X RPL A', 'is_active' => true,
    ]);
    $otherClass = Rombel::create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X RPL B', 'is_active' => true,
    ]);
    $assignedStudent = studentPolicyTestStudent('SCOPE-001');
    $otherStudent = studentPolicyTestStudent('SCOPE-002');

    Enrollment::create([
        'student_id' => $assignedStudent->id, 'academic_year_id' => $year->id,
        'rombel_id' => $assignedClass->id, 'status' => 'ACTIVE',
    ]);
    Enrollment::create([
        'student_id' => $otherStudent->id, 'academic_year_id' => $year->id,
        'rombel_id' => $otherClass->id, 'status' => 'ACTIVE',
    ]);

    $operator = User::factory()->create([
        'role_id' => Role::where('code', 'OPERATOR')->firstOrFail()->id,
    ]);
    $operator->operatorRombelScopes()->create([
        'rombel_id' => $assignedClass->id, 'is_active' => true,
    ]);

    expect($operator->can('update', $assignedStudent))->toBeTrue()
        ->and($operator->can('update', $otherStudent))->toBeFalse();
});

function studentPolicyTestStudent(string $nis): Student
{
    return Student::create([
        'nis' => $nis,
        'full_name' => 'Siswa '.$nis,
        'gender' => 'L',
        'entry_year' => 2099,
        'status' => 'AKTIF',
    ]);
}
