<?php

use App\Models\AcademicYear;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function academicSetupAdmin(): User
{
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);

    return User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);
}

test('admin can open both school setup pages', function () {
    $admin = academicSetupAdmin();
    $this->actingAs($admin)
        ->get(route('admin.academic-setup'))
        ->assertOk()
        ->assertSee('Profil sekolah');
    $this->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Pengelolaan Akun');
});

test('admin can configure a school year and create its semesters', function () {
    $admin = academicSetupAdmin();

    Livewire::actingAs($admin)
        ->test('pages.admin.academic-setup')
        ->set('schoolName', 'SMK Contoh')
        ->set('schoolNpsn', '12345678')
        ->set('schoolCity', 'Jakarta')
        ->call('saveSchool')
        ->assertHasNoErrors()
        ->set('yearName', '2099/2100')
        ->set('yearStartDate', '2099-07-01')
        ->set('yearEndDate', '2100-06-30')
        ->call('saveYear')
        ->assertHasNoErrors()
        ->assertSee('2099/2100');

    $year = AcademicYear::query()->where('name', '2099/2100')->firstOrFail();
    $this->assertDatabaseHas('schools', ['name' => 'SMK Contoh', 'npsn' => '12345678', 'city' => 'Jakarta']);
    expect($year->semesters()->count())->toBe(2)
        ->and($year->semesters()->orderBy('number')->pluck('name')->all())
        ->toBe(['Semester Ganjil', 'Semester Genap']);
});

test('only one academic year and semester per year can be active from the setup screen', function () {
    $admin = academicSetupAdmin();
    $oldYear = AcademicYear::query()->create([
        'name' => '2095/2096', 'start_date' => '2095-07-01', 'end_date' => '2096-06-30', 'status' => 'ACTIVE',
    ]);
    $oldSemester = Semester::query()->create([
        'academic_year_id' => $oldYear->id, 'number' => 1, 'name' => 'Ganjil', 'status' => 'ACTIVE',
    ]);
    $year = AcademicYear::query()->create([
        'name' => '2096/2097', 'start_date' => '2096-07-01', 'end_date' => '2097-06-30', 'status' => 'INACTIVE',
    ]);
    $first = Semester::query()->create([
        'academic_year_id' => $year->id, 'number' => 1, 'name' => 'Ganjil', 'status' => 'INACTIVE',
    ]);
    $second = Semester::query()->create([
        'academic_year_id' => $year->id, 'number' => 2, 'name' => 'Genap', 'status' => 'INACTIVE',
    ]);

    Livewire::actingAs($admin)->test('pages.admin.academic-setup')
        ->call('activateYear', $year->id)
        ->assertHasNoErrors()
        ->call('activateSemester', $first->id)
        ->assertHasNoErrors()
        ->call('activateSemester', $second->id)
        ->assertHasNoErrors();

    expect($oldYear->fresh()->status)->toBe('INACTIVE')
        ->and($oldSemester->fresh()->status)->toBe('INACTIVE')
        ->and($year->fresh()->status)->toBe('ACTIVE')
        ->and($first->fresh()->status)->toBe('INACTIVE')
        ->and($second->fresh()->status)->toBe('ACTIVE');
});

test('admin can configure classes, enroll students and create report score offerings', function () {
    $admin = academicSetupAdmin();
    $year = AcademicYear::query()->create([
        'name' => '2098/2099', 'start_date' => '2098-07-01', 'end_date' => '2099-06-30', 'status' => 'ACTIVE',
    ]);
    $semester = Semester::query()->create([
        'academic_year_id' => $year->id, 'number' => 1, 'name' => 'Semester Ganjil', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::query()->create([
        'code' => 'TEST-RPL', 'name' => 'Rekayasa Perangkat Lunak', 'short_name' => 'RPL', 'is_active' => true,
    ]);

    $component = Livewire::actingAs($admin)->test('pages.admin.academic-setup')
        ->set('rombelYearId', $year->id)
        ->set('rombelCompetencyId', $competency->id)
        ->set('rombelGrade', 10)
        ->set('rombelName', 'X RPL 1')
        ->call('saveRombel')
        ->assertHasNoErrors();
    $rombel = Rombel::query()->where('name', 'X RPL 1')->firstOrFail();

    $student = Student::query()->create([
        'nis' => 'SETUP-001', 'full_name' => 'Siswa Setup', 'gender' => 'L', 'entry_year' => 2098, 'status' => 'AKTIF',
    ]);
    $component->set('enrollmentStudentId', $student->id)
        ->set('enrollmentYearId', $year->id)
        ->set('enrollmentRombelId', $rombel->id)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $component->set('curriculumName', 'Kurikulum Sekolah')
        ->set('curriculumVersion', '2098')
        ->call('saveCurriculum')
        ->assertHasNoErrors();
    $curriculum = Curriculum::query()->where('name', 'Kurikulum Sekolah')->firstOrFail();
    $subject = Subject::query()->create([
        'code' => 'TEST-MTK', 'name' => 'Matematika', 'category' => 'GENERAL', 'is_active' => true,
    ]);
    $component->set('mappingCurriculumId', $curriculum->id)
        ->set('mappingSubjectId', $subject->id)
        ->set('mappingCompetencyId', $competency->id)
        ->set('mappingGrade', 10)
        ->set('mappingSemester', 1)
        ->call('saveMapping')
        ->assertHasNoErrors();
    $mapping = CurriculumSubject::query()->sole();

    $component->set('offeringMappingId', $mapping->id)
        ->set('offeringRombelId', $rombel->id)
        ->set('offeringSemesterId', $semester->id)
        ->call('saveOffering')
        ->assertHasNoErrors();

    expect(Enrollment::query()->where('student_id', $student->id)->where('status', 'ACTIVE')->exists())->toBeTrue()
        ->and(SubjectOffering::query()->where('rombel_id', $rombel->id)->where('is_active', true)->exists())->toBeTrue();
});

test('operator cannot open the academic setup page', function () {
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);
    $operator = User::factory()->create([
        'role_id' => Role::query()->where('code', 'OPERATOR')->firstOrFail()->id,
    ]);

    $this->actingAs($operator)->get(route('admin.academic-setup'))->assertForbidden();
});
