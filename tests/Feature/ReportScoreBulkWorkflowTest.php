<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
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
use App\Services\ReportScoreWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function bulkWorkflowFixture(): array
{
    $year = AcademicYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'status' => 'ACTIVE',
    ]);

    $semester = Semester::create([
        'academic_year_id' => $year->id,
        'number' => 1,
        'name' => 'Semester 1',
        'status' => 'ACTIVE',
    ]);

    $competency = Competency::create([
        'code' => 'RPL',
        'name' => 'Rekayasa Perangkat Lunak',
        'short_name' => 'RPL',
        'is_active' => true,
    ]);

    $rombel = Rombel::create([
        'academic_year_id' => $year->id,
        'competency_id' => $competency->id,
        'grade' => 10,
        'name' => 'X RPL 1',
        'is_active' => true,
    ]);

    $curriculum = Curriculum::create(['name' => 'Kurikulum Merdeka', 'version' => '2026', 'is_active' => true]);
    $subject1 = Subject::create(['code' => 'WEB', 'name' => 'Pemrograman Web', 'category' => 'VOCATIONAL', 'is_active' => true]);
    $subject2 = Subject::create(['code' => 'PBO', 'name' => 'Pemrograman Berorientasi Objek', 'category' => 'VOCATIONAL', 'is_active' => true]);

    $cs1 = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject1->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);
    $cs2 = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject2->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);

    $offering1 = SubjectOffering::create([
        'curriculum_subject_id' => $cs1->id,
        'rombel_id' => $rombel->id,
        'semester_id' => $semester->id,
        'pengetahuan_weight' => 50,
        'keterampilan_weight' => 50,
        'kkm' => 75,
        'is_active' => true,
    ]);

    $offering2 = SubjectOffering::create([
        'curriculum_subject_id' => $cs2->id,
        'rombel_id' => $rombel->id,
        'semester_id' => $semester->id,
        'pengetahuan_weight' => 50,
        'keterampilan_weight' => 50,
        'kkm' => 70,
        'is_active' => true,
    ]);

    $student1 = Student::create([
        'nis' => '1001',
        'full_name' => 'Ahmad Siswa',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    $student2 = Student::create([
        'nis' => '1002',
        'full_name' => 'Budi Siswa',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    Enrollment::create(['student_id' => $student1->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'ACTIVE']);
    Enrollment::create(['student_id' => $student2->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'ACTIVE']);

    $score1 = ReportScore::create([
        'student_id' => $student1->id,
        'subject_offering_id' => $offering1->id,
        'status' => 'DRAFT',
        'final_score' => 85,
    ]);

    $score2 = ReportScore::create([
        'student_id' => $student2->id,
        'subject_offering_id' => $offering1->id,
        'status' => 'DRAFT',
        'final_score' => 80,
    ]);

    $score3 = ReportScore::create([
        'student_id' => $student1->id,
        'subject_offering_id' => $offering2->id,
        'status' => 'DRAFT',
        'final_score' => 90,
    ]);

    $score4 = ReportScore::create([
        'student_id' => $student2->id,
        'subject_offering_id' => $offering2->id,
        'status' => 'DRAFT',
        'final_score' => 88,
    ]);

    $adminRole = Role::where('code', 'ADMIN')->first();
    $waliRole = Role::where('code', 'WALI_KELAS')->first();
    $kepsekRole = Role::where('code', 'KEPALA_SEKOLAH')->first();
    $studentRole = Role::where('code', 'STUDENT')->first();

    $admin = User::create([
        'name' => 'Admin Test',
        'email' => 'admin.bulk@test.local',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'status' => 'ACTIVE',
    ]);

    $wali = User::create([
        'name' => 'Wali Kelas Test',
        'email' => 'wali.bulk@test.local',
        'password' => bcrypt('password'),
        'role_id' => $waliRole->id,
        'status' => 'ACTIVE',
    ]);

    ClassAssignment::create([
        'user_id' => $wali->id,
        'rombel_id' => $rombel->id,
        'academic_year_id' => $year->id,
        'assignment_date' => now()->toDateString(),
        'status' => 'ACTIVE',
    ]);

    $kepsek = User::create([
        'name' => 'Kepala Sekolah Test',
        'email' => 'kepsek.bulk@test.local',
        'password' => bcrypt('password'),
        'role_id' => $kepsekRole->id,
        'status' => 'ACTIVE',
    ]);

    $userStudent = User::create([
        'name' => 'Siswa Test',
        'email' => 'siswa.bulk@test.local',
        'password' => bcrypt('password'),
        'role_id' => $studentRole->id,
        'student_id' => $student1->id,
        'status' => 'ACTIVE',
    ]);

    return compact(
        'year', 'semester', 'rombel', 'offering1', 'offering2',
        'student1', 'student2', 'score1', 'score2', 'score3', 'score4',
        'admin', 'wali', 'kepsek', 'userStudent'
    );
}

beforeEach(function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
});

test('wali kelas can lock all draft scores in assigned rombel and creates audit log', function () {
    $data = bulkWorkflowFixture();
    $service = app(ReportScoreWorkflowService::class);

    $count = $service->lockRombel($data['wali'], $data['rombel'], $data['semester']);

    expect($count)->toBe(4);

    $drafts = ReportScore::where('status', 'DRAFT')->count();
    $locked = ReportScore::where('status', 'LOCKED')->count();

    expect($drafts)->toBe(0)
        ->and($locked)->toBe(4);

    $auditLog = AuditLog::where('action', 'BULK_LOCK_REPORT_SCORES')
        ->where('resource_id', $data['rombel']->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->user_id)->toBe($data['wali']->id)
        ->and($auditLog->after_data['locked_count'])->toBe(4);
});

test('unauthorized user cannot lock scores for rombel', function () {
    $data = bulkWorkflowFixture();
    $service = app(ReportScoreWorkflowService::class);

    expect(fn () => $service->lockRombel($data['userStudent'], $data['rombel'], $data['semester']))
        ->toThrow(AuthorizationException::class);
});

test('finalize rombel fails if any offering is missing kkm', function () {
    $data = bulkWorkflowFixture();
    $service = app(ReportScoreWorkflowService::class);

    // Kunci nilai terlebih dahulu
    $service->lockRombel($data['admin'], $data['rombel'], $data['semester']);

    // Set offering2 KKM menjadi null
    $data['offering2']->update(['kkm' => null]);

    expect(fn () => $service->finalizeRombel($data['admin'], $data['rombel'], $data['semester']))
        ->toThrow(InvalidArgumentException::class, 'Seluruh mata pelajaran wajib memiliki KKM sebelum finalisasi.');
});

test('admin can finalize locked scores in rombel and creates audit log', function () {
    $data = bulkWorkflowFixture();
    $service = app(ReportScoreWorkflowService::class);

    // Kunci nilai lebih dulu
    $service->lockRombel($data['admin'], $data['rombel'], $data['semester']);

    $count = $service->finalizeRombel($data['admin'], $data['rombel'], $data['semester']);

    expect($count)->toBe(4);

    $finalized = ReportScore::where('status', 'FINALIZED')->count();
    expect($finalized)->toBe(4);

    $auditLog = AuditLog::where('action', 'BULK_FINALIZE_REPORT_SCORES')
        ->where('resource_id', $data['rombel']->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->user_id)->toBe($data['admin']->id)
        ->and($auditLog->after_data['finalized_count'])->toBe(4);
});

test('non admin cannot finalize rombel scores', function () {
    $data = bulkWorkflowFixture();
    $service = app(ReportScoreWorkflowService::class);

    $service->lockRombel($data['admin'], $data['rombel'], $data['semester']);

    expect(fn () => $service->finalizeRombel($data['wali'], $data['rombel'], $data['semester']))
        ->toThrow(AuthorizationException::class);
});

test('class summary livewire component allows bulk lock and bulk finalize', function () {
    $data = bulkWorkflowFixture();

    $this->actingAs($data['wali']);

    Livewire::test('pages.report-cards.class-summary', [
        'rombel' => $data['rombel'],
        'semester' => $data['semester'],
    ])
        ->call('bulkLock')
        ->assertSee('Berhasil mengunci 4 nilai rapor');

    expect(ReportScore::where('status', 'LOCKED')->count())->toBe(4);

    $this->actingAs($data['admin']);

    Livewire::test('pages.report-cards.class-summary', [
        'rombel' => $data['rombel'],
        'semester' => $data['semester'],
    ])
        ->call('bulkFinalize')
        ->assertSee('Berhasil memfinalisasi 4 nilai rapor');

    expect(ReportScore::where('status', 'FINALIZED')->count())->toBe(4);
});

test('admin and principal can view report cards overview dashboard while student is forbidden', function () {
    $data = bulkWorkflowFixture();

    // Siswa dilarang
    $this->actingAs($data['userStudent']);
    Livewire::test('pages.admin.report-cards-overview')
        ->assertForbidden();

    // Admin diizinkan
    $this->actingAs($data['admin']);
    Livewire::test('pages.admin.report-cards-overview')
        ->assertOk()
        ->assertSee('Kelengkapan Penilaian Rombel')
        ->assertSee('X RPL 1');

    // Kepala Sekolah diizinkan
    $this->actingAs($data['kepsek']);
    Livewire::test('pages.admin.report-cards-overview')
        ->assertOk()
        ->assertSee('Kelengkapan Penilaian Rombel')
        ->assertSee('X RPL 1');
});

test('admin can trigger lock and finalize from overview dashboard', function () {
    $data = bulkWorkflowFixture();

    $this->actingAs($data['admin']);

    Livewire::test('pages.admin.report-cards-overview')
        ->call('lockRombel', $data['rombel']->id)
        ->assertSee('Berhasil mengunci 4 nilai rapor');

    expect(ReportScore::where('status', 'LOCKED')->count())->toBe(4);

    Livewire::test('pages.admin.report-cards-overview')
        ->call('finalizeRombel', $data['rombel']->id)
        ->assertSee('Berhasil memfinalisasi 4 nilai rapor');

    expect(ReportScore::where('status', 'FINALIZED')->count())->toBe(4);
});
