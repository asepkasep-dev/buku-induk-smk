<?php

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\User;
use App\Services\ReportScoreWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function createReportScoreWorkflowFixture(): array
{
    $now = now();

    $academicYearId = DB::table('academic_years')->insertGetId([
        'name' => '2099/2100',
        'start_date' => '2099-07-01',
        'end_date' => '2100-06-30',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $semesterId = DB::table('semesters')->insertGetId([
        'academic_year_id' => $academicYearId,
        'number' => 1,
        'name' => 'Semester 1',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $competencyId = DB::table('competencies')->insertGetId([
        'code' => 'TEST-RPL',
        'name' => 'Rekayasa Perangkat Lunak Test',
        'short_name' => 'RPL',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $rombelId = DB::table('rombels')->insertGetId([
        'academic_year_id' => $academicYearId,
        'competency_id' => $competencyId,
        'grade' => 10,
        'name' => 'X RPL TEST',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $curriculumId = DB::table('curricula')->insertGetId([
        'name' => 'Kurikulum Test',
        'version' => '2099',
        'is_active' => true,
        'notes' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectId = DB::table('subjects')->insertGetId([
        'code' => 'TEST-WORKFLOW',
        'name' => 'Mata Pelajaran Workflow Test',
        'category' => 'GENERAL',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $curriculumSubjectId = DB::table('curriculum_subjects')->insertGetId([
        'curriculum_id' => $curriculumId,
        'subject_id' => $subjectId,
        'competency_id' => null,
        'grade' => 10,
        'semester_number' => 1,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectOfferingId = DB::table('subject_offerings')->insertGetId([
        'curriculum_subject_id' => $curriculumSubjectId,
        'rombel_id' => $rombelId,
        'semester_id' => $semesterId,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $studentId = DB::table('students')->insertGetId([
        'nis' => 'TEST-WORKFLOW-001',
        'nisn' => null,
        'nik' => null,
        'full_name' => 'Siswa Workflow Test',
        'nickname' => null,
        'gender' => 'L',
        'birth_place' => null,
        'birth_date' => null,
        'religion' => null,
        'entry_year' => 2099,
        'previous_school' => null,
        'previous_diploma_number' => null,
        'previous_diploma_date' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
        'village' => null,
        'district' => null,
        'city' => null,
        'province' => null,
        'postal_code' => null,
        'status' => 'AKTIF',
        'entry_date' => null,
        'exit_date' => null,
        'exit_notes' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'student_id' => $studentId,
        'subject_offering_id' => $subjectOfferingId,
    ];
}

test('lock changes draft report score to locked and creates audit log', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'lock') {
            return true;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 88,
        'letter_grade' => 'A',
        'description' => 'Nilai sebelum dikunci',
        'status' => 'DRAFT',
    ]);

    $service = app(ReportScoreWorkflowService::class);

    $result = $service->lock($user, $reportScore);

    expect($result->status)
        ->toBe('LOCKED')
        ->and($result->locked_by)
        ->toBe($user->id)
        ->and($result->locked_at)
        ->not->toBeNull();

    $this->assertDatabaseHas('report_scores', [
        'id' => $reportScore->id,
        'status' => 'LOCKED',
        'locked_by' => $user->id,
    ]);

    $auditLog = AuditLog::query()
        ->where('resource_type', ReportScore::class)
        ->where('resource_id', $reportScore->id)
        ->latest('id')
        ->first();

    expect($auditLog)
        ->not->toBeNull()
        ->and($auditLog->action)
        ->toBe('LOCK_REPORT_SCORE')
        ->and($auditLog->user_id)
        ->toBe($user->id)
        ->and($auditLog->before_data['status'])
        ->toBe('DRAFT')
        ->and($auditLog->after_data['status'])
        ->toBe('LOCKED')
        ->and($auditLog->after_data['locked_by'])
        ->toBe($user->id);
});

test('finalize changes locked report score to finalized and creates audit log', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'finalize') {
            return true;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 90,
        'letter_grade' => 'A',
        'description' => 'Nilai siap difinalisasi',
        'status' => 'LOCKED',
        'locked_at' => now(),
        'locked_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    $result = $service->finalize($user, $reportScore);

    expect($result->status)
        ->toBe('FINALIZED')
        ->and($result->finalized_by)
        ->toBe($user->id)
        ->and($result->finalized_at)
        ->not->toBeNull();

    $this->assertDatabaseHas('report_scores', [
        'id' => $reportScore->id,
        'status' => 'FINALIZED',
        'finalized_by' => $user->id,
    ]);

    $auditLog = AuditLog::query()
        ->where('resource_type', ReportScore::class)
        ->where('resource_id', $reportScore->id)
        ->where('action', 'FINALIZE_REPORT_SCORE')
        ->first();

    expect($auditLog)
        ->not->toBeNull()
        ->and($auditLog->user_id)
        ->toBe($user->id)
        ->and($auditLog->before_data['status'])
        ->toBe('LOCKED')
        ->and($auditLog->after_data['status'])
        ->toBe('FINALIZED')
        ->and($auditLog->after_data['finalized_by'])
        ->toBe($user->id);
});

test('correct updates finalized report score and creates audit log with reason', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'correct') {
            return true;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 85,
        'letter_grade' => 'B',
        'description' => 'Nilai sebelum koreksi',
        'status' => 'FINALIZED',
        'locked_at' => now(),
        'locked_by' => $user->id,
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    $result = $service->correct(
        $user,
        $reportScore,
        92,
        'A',
        'Nilai setelah koreksi',
        'Kesalahan input nilai awal'
    );

    expect($result->final_score)
        ->toBe(92)
        ->and($result->letter_grade)
        ->toBe('A')
        ->and($result->description)
        ->toBe('Nilai setelah koreksi')
        ->and($result->status)
        ->toBe('FINALIZED');

    $this->assertDatabaseHas('report_scores', [
        'id' => $reportScore->id,
        'final_score' => 92,
        'letter_grade' => 'A',
        'description' => 'Nilai setelah koreksi',
        'status' => 'FINALIZED',
    ]);

    $auditLog = AuditLog::query()
        ->where('resource_type', ReportScore::class)
        ->where('resource_id', $reportScore->id)
        ->where('action', 'CORRECT_FINALIZED_REPORT_SCORE')
        ->first();

    expect($auditLog)
        ->not->toBeNull()
        ->and($auditLog->user_id)
        ->toBe($user->id)
        ->and($auditLog->before_data['final_score'])
        ->toBe(85)
        ->and($auditLog->after_data['final_score'])
        ->toBe(92)
        ->and($auditLog->after_data['status'])
        ->toBe('FINALIZED')
        ->and($auditLog->after_data['correction_reason'])
        ->toBe('Kesalahan input nilai awal');
});

test('correct rejects empty correction reason', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'correct') {
            return true;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 85,
        'letter_grade' => 'B',
        'description' => 'Nilai sebelum koreksi',
        'status' => 'FINALIZED',
        'locked_at' => now(),
        'locked_by' => $user->id,
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    expect(
        fn () => $service->correct(
            $user,
            $reportScore,
            90,
            'A',
            'Nilai setelah koreksi',
            '   '
        )
    )->toThrow(
        InvalidArgumentException::class,
        'Alasan koreksi wajib diisi.'
    );

    $reportScore->refresh();

    expect($reportScore->final_score)
        ->toBe(85)
        ->and($reportScore->status)
        ->toBe('FINALIZED');

    $this->assertDatabaseMissing('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $reportScore->id,
        'action' => 'CORRECT_FINALIZED_REPORT_SCORE',
    ]);
});

test('correct rejects final score outside valid range', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'correct') {
            return true;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 85,
        'letter_grade' => 'B',
        'description' => 'Nilai sebelum koreksi',
        'status' => 'FINALIZED',
        'locked_at' => now(),
        'locked_by' => $user->id,
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    expect(
        fn () => $service->correct(
            $user,
            $reportScore,
            101,
            'A',
            'Nilai tidak valid',
            'Uji nilai di atas batas'
        )
    )->toThrow(
        InvalidArgumentException::class,
        'Nilai akhir harus antara 0 sampai 100.'
    );

    $reportScore->refresh();

    expect($reportScore->final_score)
        ->toBe(85)
        ->and($reportScore->status)
        ->toBe('FINALIZED');

    $this->assertDatabaseMissing('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $reportScore->id,
        'action' => 'CORRECT_FINALIZED_REPORT_SCORE',
    ]);
});

test('lock rejects unauthorized user', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'lock') {
            return false;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 88,
        'letter_grade' => 'A',
        'description' => 'Nilai sebelum lock',
        'status' => 'DRAFT',
    ]);

    $service = app(ReportScoreWorkflowService::class);

    expect(
        fn () => $service->lock($user, $reportScore)
    )->toThrow(
        Illuminate\Auth\Access\AuthorizationException::class
    );

    $reportScore->refresh();

    expect($reportScore->status)
        ->toBe('DRAFT')
        ->and($reportScore->locked_at)
        ->toBeNull()
        ->and($reportScore->locked_by)
        ->toBeNull();

    $this->assertDatabaseMissing('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $reportScore->id,
        'action' => 'LOCK_REPORT_SCORE',
    ]);
});

test('finalize rejects unauthorized user', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'finalize') {
            return false;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 90,
        'letter_grade' => 'A',
        'description' => 'Nilai sebelum finalisasi',
        'status' => 'LOCKED',
        'locked_at' => now(),
        'locked_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    expect(
        fn () => $service->finalize($user, $reportScore)
    )->toThrow(
        Illuminate\Auth\Access\AuthorizationException::class
    );

    $reportScore->refresh();

    expect($reportScore->status)
        ->toBe('LOCKED')
        ->and($reportScore->finalized_at)
        ->toBeNull()
        ->and($reportScore->finalized_by)
        ->toBeNull();

    $this->assertDatabaseMissing('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $reportScore->id,
        'action' => 'FINALIZE_REPORT_SCORE',
    ]);
});

test('correct rejects unauthorized user', function () {
    $user = User::factory()->create();

    Gate::before(function (User $gateUser, string $ability) use ($user) {
        if ($gateUser->is($user) && $ability === 'correct') {
            return false;
        }

        return null;
    });

    $fixture = createReportScoreWorkflowFixture();

    $reportScore = ReportScore::create([
        'student_id' => $fixture['student_id'],
        'subject_offering_id' => $fixture['subject_offering_id'],
        'final_score' => 85,
        'letter_grade' => 'B',
        'description' => 'Nilai sebelum koreksi',
        'status' => 'FINALIZED',
        'locked_at' => now(),
        'locked_by' => $user->id,
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $service = app(ReportScoreWorkflowService::class);

    expect(
        fn () => $service->correct(
            $user,
            $reportScore,
            95,
            'A',
            'Nilai setelah koreksi',
            'Uji otorisasi koreksi'
        )
    )->toThrow(
        Illuminate\Auth\Access\AuthorizationException::class
    );

    $reportScore->refresh();

    expect($reportScore->final_score)
        ->toBe(85)
        ->and($reportScore->letter_grade)
        ->toBe('B')
        ->and($reportScore->description)
        ->toBe('Nilai sebelum koreksi')
        ->and($reportScore->status)
        ->toBe('FINALIZED');

    $this->assertDatabaseMissing('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $reportScore->id,
        'action' => 'CORRECT_FINALIZED_REPORT_SCORE',
    ]);
});