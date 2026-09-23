<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
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

/** @return array{student: Student, offering: SubjectOffering, enrollment: Enrollment} */
function reportScoreLivewireFixture(): array
{
    $year = AcademicYear::create([
        'name' => '2099/2100', 'start_date' => '2099-07-01',
        'end_date' => '2100-06-30', 'status' => 'ACTIVE',
    ]);
    $semester = Semester::create([
        'academic_year_id' => $year->id, 'number' => 1,
        'name' => 'Semester 1', 'status' => 'ACTIVE',
    ]);
    $competency = Competency::create([
        'code' => 'LW-RPL', 'name' => 'Rekayasa Perangkat Lunak',
        'short_name' => 'RPL', 'is_active' => true,
    ]);
    $rombel = Rombel::create([
        'academic_year_id' => $year->id, 'competency_id' => $competency->id,
        'grade' => 10, 'name' => 'X RPL Livewire', 'is_active' => true,
    ]);
    $curriculum = Curriculum::create([
        'name' => 'Kurikulum Livewire', 'version' => '2099', 'is_active' => true,
    ]);
    $subject = Subject::create([
        'code' => 'LW-MTK', 'name' => 'Matematika Livewire',
        'category' => 'GENERAL', 'is_active' => true,
    ]);
    $curriculumSubject = CurriculumSubject::create([
        'curriculum_id' => $curriculum->id, 'subject_id' => $subject->id,
        'grade' => 10, 'semester_number' => 1, 'is_active' => true,
    ]);
    $offering = SubjectOffering::create([
        'curriculum_subject_id' => $curriculumSubject->id,
        'rombel_id' => $rombel->id, 'semester_id' => $semester->id, 'is_active' => true,
    ]);
    $student = Student::create([
        'nis' => 'LW-001', 'full_name' => 'Siswa Pengujian Livewire',
        'gender' => 'L', 'entry_year' => 2099, 'status' => 'AKTIF',
    ]);
    $enrollment = Enrollment::create([
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'rombel_id' => $rombel->id, 'status' => 'ACTIVE',
    ]);

    return compact('student', 'offering', 'enrollment');
}

function reportScoreLivewireUser(string $roleCode, Student $student, bool $withScope = true): User
{
    $user = User::factory()->create([
        'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
        'student_id' => $roleCode === 'STUDENT' ? $student->id : null,
    ]);
    $enrollment = $student->enrollments()->firstOrFail();
    if ($withScope && $roleCode === 'OPERATOR') {
        $user->operatorRombelScopes()->create([
            'rombel_id' => $enrollment->rombel_id, 'is_active' => true,
        ]);
    }
    if ($withScope && $roleCode === 'WALI_KELAS') {
        $user->classAssignments()->create([
            'rombel_id' => $enrollment->rombel_id,
            'academic_year_id' => $enrollment->academic_year_id, 'status' => 'ACTIVE',
        ]);
    }

    return $user;
}

function reportScoreLivewireRecord(array $fixture, string $status = 'DRAFT'): ReportScore
{
    return ReportScore::create([
        'student_id' => $fixture['student']->id,
        'subject_offering_id' => $fixture['offering']->id,
        'final_score' => 81, 'letter_grade' => 'B',
        'description' => 'Pemahaman materi baik.', 'status' => $status,
    ]);
}

test('report score pages show actions appropriate to role and status', function (
    string $role, string $status, string $statusLabel, string $listAction, array $visibleActions
) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture, $status);
    $user = reportScoreLivewireUser($role, $fixture['student']);

    Livewire::actingAs($user)
        ->test('pages.report-scores.index', ['student' => $fixture['student']])
        ->assertSee('Matematika Livewire')
        ->assertSee('81')
        ->assertSee($statusLabel)
        ->assertSeeHtml('href="'.route('report-scores.edit', $score).'"')
        ->assertSeeText($listAction);

    $page = Livewire::actingAs($user)
        ->test('pages.report-scores.edit', ['reportScore' => $score])
        ->assertSee($fixture['student']->full_name)
        ->assertSee($status);

    foreach (['wire:submit="save"', 'wire:click="lockScore"', 'wire:click="finalizeScore"', 'wire:submit="correctScore"'] as $action) {
        if (in_array($action, $visibleActions, true)) {
            $page->assertSeeHtml($action);
        } else {
            $page->assertDontSeeHtml($action);
        }
    }
})->with([
    'student draft' => ['STUDENT', 'DRAFT', 'Draft', 'Lihat', []],
    'student locked' => ['STUDENT', 'LOCKED', 'Terkunci', 'Lihat', []],
    'student finalized' => ['STUDENT', 'FINALIZED', 'Final', 'Lihat', []],
    'admin draft' => ['ADMIN', 'DRAFT', 'Draft', 'Edit', ['wire:submit="save"', 'wire:click="lockScore"']],
    'admin locked' => ['ADMIN', 'LOCKED', 'Terkunci', 'Proses', ['wire:click="finalizeScore"']],
    'admin finalized' => ['ADMIN', 'FINALIZED', 'Final', 'Lihat', ['wire:submit="correctScore"']],
    'operator draft' => ['OPERATOR', 'DRAFT', 'Draft', 'Edit', ['wire:submit="save"', 'wire:click="lockScore"']],
    'operator locked' => ['OPERATOR', 'LOCKED', 'Terkunci', 'Lihat', []],
    'operator finalized' => ['OPERATOR', 'FINALIZED', 'Final', 'Lihat', []],
    'homeroom draft' => ['WALI_KELAS', 'DRAFT', 'Draft', 'Edit', ['wire:submit="save"', 'wire:click="lockScore"']],
    'homeroom locked' => ['WALI_KELAS', 'LOCKED', 'Terkunci', 'Lihat', []],
    'homeroom finalized' => ['WALI_KELAS', 'FINALIZED', 'Final', 'Lihat', []],
    'principal draft' => ['KEPALA_SEKOLAH', 'DRAFT', 'Draft', 'Lihat', []],
    'principal locked' => ['KEPALA_SEKOLAH', 'LOCKED', 'Terkunci', 'Lihat', []],
    'principal finalized' => ['KEPALA_SEKOLAH', 'FINALIZED', 'Final', 'Lihat', []],
]);

test('student sees own draft values without editable fields', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture);
    $user = reportScoreLivewireUser('STUDENT', $fixture['student']);

    $this->actingAs($user)->get(route('report-scores.edit', $score))
        ->assertOk()->assertSeeLivewire('pages.report-scores.edit');

    Livewire::actingAs($user)->test('pages.report-scores.edit', ['reportScore' => $score])
        ->assertSee('Nilai masih berstatus draft dan hanya dapat dilihat.')
        ->assertSee('81')->assertSee('B')->assertSee('Pemahaman materi baik.')
        ->assertDontSeeHtml('wire:model="finalScore"')
        ->assertDontSeeHtml('wire:model="letterGrade"')
        ->assertDontSeeHtml('wire:model="description"');
});

test('student cannot invoke a report score write action directly', function (string $status, string $action) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture, $status);
    $user = reportScoreLivewireUser('STUDENT', $fixture['student']);

    Livewire::actingAs($user)->test('pages.report-scores.edit', ['reportScore' => $score])
        ->set('finalScore', 99)->set('correctionFinalScore', 99)
        ->set('correctionReason', 'Percobaan perubahan nilai')
        ->call($action)->assertForbidden();

    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 81, 'status' => $status]);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([
    'save draft' => ['DRAFT', 'save'], 'lock draft' => ['DRAFT', 'lockScore'],
    'finalize locked' => ['LOCKED', 'finalizeScore'], 'correct final' => ['FINALIZED', 'correctScore'],
]);

test('admin cannot save a locked or finalized score through the ordinary form', function (string $status) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture, $status);
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);

    Livewire::actingAs($admin)->test('pages.report-scores.edit', ['reportScore' => $score])
        ->set('finalScore', 99)->call('save')->assertForbidden();

    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 81, 'status' => $status]);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['LOCKED', 'FINALIZED']);

test('student cannot open another student report score detail', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture);
    $user = reportScoreLivewireUser('STUDENT', $fixture['student']);
    $other = Student::create([
        'nis' => 'LW-002', 'full_name' => 'Siswa Lain', 'gender' => 'P',
        'entry_year' => 2099, 'status' => 'AKTIF',
    ]);
    $user->update(['student_id' => $other->id]);

    $this->actingAs($user)->get(route('report-scores.edit', $score))
        ->assertForbidden()->assertDontSee($fixture['student']->full_name);
});

test('student cannot open the create report score page directly', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $user = reportScoreLivewireUser('STUDENT', $fixture['student']);

    $this->actingAs($user)->get(route('report-scores.create', $fixture['student']))->assertForbidden();
    $this->assertDatabaseCount('report_scores', 0);
});

test('staff without a class assignment cannot access report score pages', function (string $role) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture);
    $user = reportScoreLivewireUser($role, $fixture['student'], false);

    $this->actingAs($user)->get(route('report-scores.index', $fixture['student']))->assertForbidden();
    $this->get(route('report-scores.create', $fixture['student']))->assertForbidden();
    $this->get(route('report-scores.edit', $score))->assertForbidden();
})->with(['OPERATOR', 'WALI_KELAS']);

test('guests are redirected to login on report score pages', function () {
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture);

    $this->get(route('report-scores.index', $fixture['student']))->assertRedirect(route('login'));
    $this->get(route('report-scores.create', $fixture['student']))->assertRedirect(route('login'));
    $this->get(route('report-scores.edit', $score))->assertRedirect(route('login'));
});

test('admin can create a draft report score with an audit trail', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);

    $this->actingAs($admin)->get(route('report-scores.create', $fixture['student']))
        ->assertOk()->assertSeeLivewire('pages.report-scores.create');

    $page = Livewire::actingAs($admin)->test('pages.report-scores.create', ['student' => $fixture['student']])
        ->assertSee('Matematika Livewire')->set('subjectOfferingId', $fixture['offering']->id)
        ->set('finalScore', 85)->set('letterGrade', 'B')->set('description', 'Nilai awal')
        ->call('save')->assertHasNoErrors();

    $this->assertDatabaseCount('report_scores', 1);
    $score = ReportScore::sole();
    $this->assertDatabaseHas('report_scores', [
        'id' => $score->id, 'student_id' => $fixture['student']->id,
        'subject_offering_id' => $fixture['offering']->id,
        'final_score' => 85, 'letter_grade' => 'B', 'description' => 'Nilai awal', 'status' => 'DRAFT',
    ]);
    $page->assertRedirect(route('report-scores.edit', $score));
    $log = AuditLog::sole();
    expect($log->action)->toBe('CREATE_REPORT_SCORE');
    expect($log->user_id)->toBe($admin->id);
    expect($log->after_data)->toMatchArray(['id' => $score->id, 'final_score' => 85, 'status' => 'DRAFT']);
});

test('create excludes an existing score and rejects a duplicate submitted from an open form', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);
    $page = Livewire::actingAs($admin)->test('pages.report-scores.create', ['student' => $fixture['student']])
        ->assertSee('Matematika Livewire');
    $score = reportScoreLivewireRecord($fixture);

    $page->set('subjectOfferingId', $fixture['offering']->id)->set('finalScore', 99)
        ->call('save')->assertHasErrors(['subjectOfferingId'])
        ->assertSee('Nilai untuk mata pelajaran dan semester ini sudah ada.')->assertNoRedirect();

    Livewire::actingAs($admin)->test('pages.report-scores.create', ['student' => $fixture['student']])
        ->assertSet('offeringOptions', [])->assertSee('Semua mata pelajaran sudah memiliki nilai.')
        ->assertDontSeeHtml('wire:submit="save"');
    $this->assertDatabaseCount('report_scores', 1);
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 81]);
    $this->assertDatabaseCount('audit_logs', 0);
});

test('create rejects an inactive subject offering even when submitted directly', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);
    $page = Livewire::actingAs($admin)->test('pages.report-scores.create', ['student' => $fixture['student']]);
    $fixture['offering']->update(['is_active' => false]);

    $page->set('subjectOfferingId', $fixture['offering']->id)->call('save')
        ->assertHasErrors(['subjectOfferingId'])
        ->assertSee('Mata pelajaran tidak sesuai dengan rombel aktif siswa.');
    $this->assertDatabaseCount('report_scores', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

test('report score forms reject scores outside zero to one hundred', function (int $value, string $pageName) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);
    $score = $pageName === 'edit' ? reportScoreLivewireRecord($fixture) : null;
    $params = $score ? ['reportScore' => $score] : ['student' => $fixture['student']];
    $page = Livewire::actingAs($admin)->test('pages.report-scores.'.$pageName, $params);
    if (! $score) {
        $page->set('subjectOfferingId', $fixture['offering']->id);
    }

    $page->set('finalScore', $value)->call('save')->assertHasErrors(['finalScore' => $value < 0 ? 'min' : 'max']);
    $this->assertDatabaseCount('report_scores', $score ? 1 : 0);
    if ($score) {
        $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 81]);
    }
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['negative' => [-1], 'above maximum' => [101]])->with(['create', 'edit']);

test('admin can save lock finalize and correct a score with an audit trail', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture);
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);

    $page = Livewire::actingAs($admin)->test('pages.report-scores.edit', ['reportScore' => $score])
        ->set('finalScore', 90)->set('letterGrade', 'A')->set('description', 'Nilai diperbarui')
        ->call('save')->assertHasNoErrors()->assertSee('Nilai rapor berhasil disimpan.');
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 90, 'status' => 'DRAFT']);

    $page->call('lockScore')->assertHasNoErrors()->assertSee('Nilai rapor berhasil dikunci.')
        ->assertDontSeeHtml('wire:submit="save"')->assertSeeHtml('wire:click="finalizeScore"');
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'status' => 'LOCKED', 'locked_by' => $admin->id]);

    $page->call('finalizeScore')->assertHasNoErrors()->assertSee('Nilai rapor berhasil difinalisasi.')
        ->assertSeeHtml('wire:submit="correctScore"')->assertDontSeeHtml('wire:click="finalizeScore"');
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'status' => 'FINALIZED', 'finalized_by' => $admin->id]);

    $page->set('correctionFinalScore', 95)->set('correctionDescription', 'Nilai setelah koreksi')
        ->set('correctionReason', 'Perbaikan kesalahan input')
        ->call('correctScore')->assertHasNoErrors()->assertSee('Nilai final berhasil dikoreksi.')
        ->assertSee('Perbaikan kesalahan input')->assertSet('finalScore', 95)->assertSet('correctionReason', '');
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 95, 'status' => 'FINALIZED']);
    $logs = AuditLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe([
        'UPDATE_REPORT_SCORE', 'LOCK_REPORT_SCORE', 'FINALIZE_REPORT_SCORE', 'CORRECT_FINALIZED_REPORT_SCORE',
    ]);
    expect($logs->pluck('user_id')->unique()->all())->toBe([$admin->id]);
    expect($logs->pluck('resource_id')->unique()->all())->toBe([$score->id]);
    expect($logs->last()->before_data['final_score'])->toBe(90);
    expect($logs->last()->after_data)->toMatchArray(['final_score' => 95, 'correction_reason' => 'Perbaikan kesalahan input']);
});

test('final score correction requires a reason and does not write on failure', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = reportScoreLivewireFixture();
    $score = reportScoreLivewireRecord($fixture, 'FINALIZED');
    $admin = reportScoreLivewireUser('ADMIN', $fixture['student']);

    Livewire::actingAs($admin)->test('pages.report-scores.edit', ['reportScore' => $score])
        ->set('correctionFinalScore', 99)->set('correctionReason', '')
        ->call('correctScore')->assertHasErrors(['correctionReason' => 'required']);
    $this->assertDatabaseHas('report_scores', ['id' => $score->id, 'final_score' => 81, 'status' => 'FINALIZED']);
    $this->assertDatabaseCount('audit_logs', 0);
});
