<?php

use App\Models\AcademicYear;
use App\Models\ClassAssignment;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\ReportScore;
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

function reportCardFixture(): array
{
    $year = AcademicYear::create([
        'name' => '2026/2027', 'start_date' => '2026-07-01',
        'end_date' => '2027-06-30', 'status' => 'ACTIVE',
    ]);
    $semester = Semester::create([
        'academic_year_id' => $year->id, 'number' => 1,
        'name' => 'Semester 1', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::create([
        'code' => 'RPL', 'name' => 'RPL', 'short_name' => 'RPL', 'is_active' => true,
    ]);
    $rombel = Rombel::create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X RPL Rapor', 'is_active' => true,
    ]);
    $curriculum = Curriculum::create(['name' => 'Kurikulum Rapor', 'version' => '2026', 'is_active' => true]);
    $subject = Subject::create(['code' => 'WEB', 'name' => 'Pemrograman Web', 'category' => 'VOCATIONAL', 'is_active' => true]);
    $cs = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);
    $offering = SubjectOffering::create([
        'curriculum_subject_id' => $cs->id, 'rombel_id' => $rombel->id,
        'semester_id' => $semester->id, 'pengetahuan_weight' => 50,
        'keterampilan_weight' => 50, 'kkm' => 75, 'is_active' => true,
    ]);

    $student1 = Student::create([
        'nis' => '11111', 'full_name' => 'Siswa Rapor Satu', 'gender' => 'L',
        'entry_year' => 2026, 'status' => 'AKTIF',
    ]);
    $student2 = Student::create([
        'nis' => '22222', 'full_name' => 'Siswa Rapor Dua', 'gender' => 'P',
        'entry_year' => 2026, 'status' => 'AKTIF',
    ]);

    Enrollment::create(['student_id' => $student1->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'ACTIVE']);
    Enrollment::create(['student_id' => $student2->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'ACTIVE']);

    ReportScore::create([
        'student_id' => $student1->id, 'subject_offering_id' => $offering->id,
        'pengetahuan_avg' => 88.0, 'keterampilan_avg' => 90.0,
        'final_score' => 89, 'letter_grade' => 'B', 'status' => 'FINALIZED',
        'description' => 'Sangat kompeten pada modul web.',
    ]);

    return compact('year', 'semester', 'rombel', 'offering', 'student1', 'student2');
}

function makeRoleUser(string $roleCode, ?int $studentId = null): User
{
    return User::factory()->create([
        'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
        'student_id' => $studentId,
    ]);
}

test('siswa hanya dapat melihat rapor miliknya sendiri', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = reportCardFixture();

    $student1User = makeRoleUser('STUDENT', $f['student1']->id);
    $student2User = makeRoleUser('STUDENT', $f['student2']->id);

    Livewire::actingAs($student1User)
        ->test('pages.report-cards.show', ['student' => $f['student1'], 'semester' => $f['semester']])
        ->assertOk()
        ->assertSee('Siswa Rapor Satu')
        ->assertSee('Pemrograman Web')
        ->assertSee('89');

    Livewire::actingAs($student2User)
        ->test('pages.report-cards.show', ['student' => $f['student1'], 'semester' => $f['semester']])
        ->assertForbidden();
});

test('wali kelas dapat melihat rapor siswa kelasnya tetapi ditolak untuk siswa kelas lain', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = reportCardFixture();

    $waliUser = makeRoleUser('WALI_KELAS');
    ClassAssignment::create([
        'user_id' => $waliUser->id,
        'rombel_id' => $f['rombel']->id,
        'academic_year_id' => $f['year']->id,
        'status' => 'ACTIVE',
    ]);

    Livewire::actingAs($waliUser)
        ->test('pages.report-cards.show', ['student' => $f['student1'], 'semester' => $f['semester']])
        ->assertOk()
        ->assertSee('Siswa Rapor Satu');

    $otherRombel = Rombel::create([
        'academic_year_id' => $f['year']->id,
        'competency_id' => $f['rombel']->competency_id,
        'grade' => 10,
        'name' => 'X RPL Lain',
        'is_active' => true,
    ]);
    $otherStudent = Student::create(['nis' => '33333', 'full_name' => 'Siswa Luar', 'gender' => 'L', 'entry_year' => 2026, 'status' => 'AKTIF']);
    Enrollment::create(['student_id' => $otherStudent->id, 'rombel_id' => $otherRombel->id, 'academic_year_id' => $f['year']->id, 'status' => 'ACTIVE']);

    Livewire::actingAs($waliUser)
        ->test('pages.report-cards.show', ['student' => $otherStudent, 'semester' => $f['semester']])
        ->assertForbidden();
});

test('rekap kelas menampilkan status kelengkapan nilai dan mengunci akses siswa', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = reportCardFixture();

    $waliUser = makeRoleUser('WALI_KELAS');
    ClassAssignment::create([
        'user_id' => $waliUser->id,
        'rombel_id' => $f['rombel']->id,
        'academic_year_id' => $f['year']->id,
        'status' => 'ACTIVE',
    ]);

    $studentUser = makeRoleUser('STUDENT', $f['student1']->id);

    // Wali kelas can view
    Livewire::actingAs($waliUser)
        ->test('pages.report-cards.class-summary', ['rombel' => $f['rombel'], 'semester' => $f['semester']])
        ->assertOk()
        ->assertSee('X RPL Rapor')
        ->assertSee('Siswa Rapor Satu')
        ->assertSee('Siswa Rapor Dua')
        ->assertSee('Lengkap')
        ->assertSee('Belum Ada');

    // Student is forbidden
    Livewire::actingAs($studentUser)
        ->test('pages.report-cards.class-summary', ['rombel' => $f['rombel'], 'semester' => $f['semester']])
        ->assertForbidden();
});
