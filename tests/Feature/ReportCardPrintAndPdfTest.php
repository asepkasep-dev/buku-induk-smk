<?php

use App\Models\AcademicYear;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\ReportScore;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function printPdfFixture(): array
{
    $school = School::create([
        'name' => 'SMK Negeri 1 Print',
        'npsn' => '12345678',
        'city' => 'Bandung',
        'principal_name' => 'Dr. H. Ahmad, M.Pd.',
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027', 'start_date' => '2026-07-01',
        'end_date' => '2027-06-30', 'status' => 'ACTIVE',
    ]);
    $semester = Semester::create([
        'academic_year_id' => $year->id, 'number' => 1,
        'name' => 'Semester 1', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::create(['code' => 'RPL', 'name' => 'RPL', 'short_name' => 'RPL', 'is_active' => true]);
    $rombel = Rombel::create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X RPL Print', 'is_active' => true,
    ]);
    $curriculum = Curriculum::create(['name' => 'Kurikulum Merdeka', 'version' => '2026', 'is_active' => true]);
    $subject = Subject::create(['code' => 'KOD', 'name' => 'Coding Dasar', 'category' => 'VOCATIONAL', 'is_active' => true]);
    $cs = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);
    $offering = SubjectOffering::create([
        'curriculum_subject_id' => $cs->id, 'rombel_id' => $rombel->id,
        'semester_id' => $semester->id, 'pengetahuan_weight' => 50,
        'keterampilan_weight' => 50, 'kkm' => 75, 'is_active' => true,
    ]);

    $student = Student::create([
        'nis' => '77777', 'full_name' => 'Bintang Pelajar', 'gender' => 'L',
        'entry_year' => 2026, 'status' => 'AKTIF',
    ]);
    Enrollment::create(['student_id' => $student->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'ACTIVE']);

    ReportScore::create([
        'student_id' => $student->id, 'subject_offering_id' => $offering->id,
        'pengetahuan_avg' => 90.0, 'keterampilan_avg' => 92.0,
        'final_score' => 91, 'letter_grade' => 'A', 'status' => 'FINALIZED',
        'description' => 'Sangat menguasai coding dasar.',
    ]);

    return compact('school', 'year', 'semester', 'rombel', 'student');
}

test('rute print browser menampilkan layout rapor A4 presisi', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = printPdfFixture();

    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->first()->id,
    ]);

    $response = $this->actingAs($admin)->get(route('report-cards.print', [
        'student' => $f['student']->id,
        'semester' => $f['semester']->id,
    ]));

    $response->assertOk()
        ->assertSee('Bintang Pelajar')
        ->assertSee('Coding Dasar')
        ->assertSee('91')
        ->assertSee('window.print()', false);
});

test('rute PDF rapor mengembalikan dokumen PDF', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = printPdfFixture();

    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->first()->id,
    ]);

    $response = $this->actingAs($admin)->get(route('report-cards.pdf', [
        'student' => $f['student']->id,
        'semester' => $f['semester']->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('rute batch export PDF rombel menghasilkan file ZIP', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $f = printPdfFixture();

    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->first()->id,
    ]);

    $response = $this->actingAs($admin)->get(route('report-cards.batch-pdf', [
        'rombel' => $f['rombel']->id,
        'semester' => $f['semester']->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('zip');
});

test('admin dapat mengunggah logo sekolah di pengaturan akademik', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    Storage::fake('public');

    $admin = User::factory()->create([
        'role_id' => Role::where('code', 'ADMIN')->first()->id,
    ]);

    $file = UploadedFile::fake()->image('logo.png', 100, 100);

    Livewire::actingAs($admin)
        ->test('pages.admin.academic-setup')
        ->set('schoolName', 'SMK Negeri Unggul')
        ->set('schoolLogo', $file)
        ->call('saveSchool')
        ->assertHasNoErrors();

    $school = School::first();
    expect($school->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($school->logo_path);
});
