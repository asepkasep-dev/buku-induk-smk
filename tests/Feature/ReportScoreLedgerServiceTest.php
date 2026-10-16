<?php

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\ReportScoreItem;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Services\ReportScoreLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeLedgerFixture(int $penWeight = 60, int $ketWeight = 40, ?int $kkm = 75): array
{
    $now = now();

    $academicYearId = DB::table('academic_years')->insertGetId([
        'name' => '2099/2100', 'start_date' => '2099-07-01', 'end_date' => '2100-06-30',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $semesterId = DB::table('semesters')->insertGetId([
        'academic_year_id' => $academicYearId, 'number' => 1, 'name' => 'Semester 1',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $competencyId = DB::table('competencies')->insertGetId([
        'code' => 'LEDGER-RPL', 'name' => 'RPL Ledger', 'short_name' => 'RPL',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $rombelId = DB::table('rombels')->insertGetId([
        'academic_year_id' => $academicYearId, 'competency_id' => $competencyId, 'grade' => 10,
        'name' => 'X RPL LEDGER', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $curriculumId = DB::table('curricula')->insertGetId([
        'name' => 'Kurikulum Ledger', 'version' => '2099', 'is_active' => true,
        'notes' => null, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $subjectId = DB::table('subjects')->insertGetId([
        'code' => 'LEDGER-WEB', 'name' => 'Pemrograman Web Ledger', 'category' => 'GENERAL',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $curriculumSubjectId = DB::table('curriculum_subjects')->insertGetId([
        'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'competency_id' => null,
        'grade' => 10, 'semester_number' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $subjectOfferingId = DB::table('subject_offerings')->insertGetId([
        'curriculum_subject_id' => $curriculumSubjectId, 'rombel_id' => $rombelId, 'semester_id' => $semesterId,
        'pengetahuan_weight' => $penWeight, 'keterampilan_weight' => $ketWeight, 'kkm' => $kkm,
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $studentId = DB::table('students')->insertGetId([
        'nis' => 'LEDGER-001', 'nisn' => null, 'nik' => null, 'full_name' => 'Siswa Ledger',
        'nickname' => null, 'gender' => 'L', 'birth_place' => null, 'birth_date' => null,
        'religion' => null, 'entry_year' => 2099, 'previous_school' => null,
        'previous_diploma_number' => null, 'previous_diploma_date' => null, 'phone' => null,
        'email' => null, 'address' => null, 'village' => null, 'district' => null, 'city' => null,
        'province' => null, 'postal_code' => null, 'status' => 'AKTIF', 'entry_date' => null,
        'exit_date' => null, 'exit_notes' => null, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $reportScoreId = DB::table('report_scores')->insertGetId([
        'student_id' => $studentId, 'subject_offering_id' => $subjectOfferingId,
        'status' => 'DRAFT', 'created_at' => $now, 'updated_at' => $now,
    ]);

    return [
        'offering_id' => $subjectOfferingId,
        'report_score_id' => $reportScoreId,
    ];
}

function draftReportScore(array $fixture): ReportScore
{
    return ReportScore::query()->findOrFail($fixture['report_score_id']);
}

test('addItem menghitung rata-rata, nilai akhir, predikat, dan deskripsi', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture(60, 40, 75);
    $service = app(ReportScoreLedgerService::class);

    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'UH 1', 80);
    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_KETERAMPILAN, 'Praktikum', 70);

    $reportScore = draftReportScore($fixture)->refresh();

    // pen avg 80, ket avg 70 => (80*60 + 70*40)/100 = 76 ; KKM 75 => C (75..83)
    expect((float) $reportScore->pengetahuan_avg)->toBe(80.0)
        ->and((float) $reportScore->keterampilan_avg)->toBe(70.0)
        ->and($reportScore->final_score)->toBe(76)
        ->and($reportScore->letter_grade)->toBe('C')
        ->and($reportScore->description)->not->toBeNull()
        ->and($reportScore->description)->toContain('76');

    $this->assertDatabaseCount('report_score_items', 2);
});

test('updateItem memengaruhi ulang nilai akhir', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture(50, 50, 70);
    $service = app(ReportScoreLedgerService::class);

    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'UH', 60);
    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_KETERAMPILAN, 'PK', 60);

    $item = ReportScoreItem::query()->where('type', ReportScoreItem::TYPE_PENGETAHUAN)->first();
    $service->updateItem($user, $item, 'UH', 100);

    // pen avg 100, ket avg 60 => (100+60)/2 = 80 ; KKM 70 => B (>=80)
    expect(draftReportScore($fixture)->refresh()->final_score)->toBe(80)
        ->and(draftReportScore($fixture)->refresh()->letter_grade)->toBe('B');
});

test('removeItem menghapus item dan menghitung ulang', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture(60, 40, 75);
    $service = app(ReportScoreLedgerService::class);

    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'A', 90);
    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_KETERAMPILAN, 'B', 90);

    $item = ReportScoreItem::query()->where('type', ReportScoreItem::TYPE_KETERAMPILAN)->first();
    $service->removeItem($user, $item);

    $reportScore = draftReportScore($fixture)->refresh();

    expect($reportScore->keterampilan_avg)->toBeNull()
        ->and($reportScore->final_score)->toBeNull()
        ->and($reportScore->letter_grade)->toBeNull();
});

test('updateOfferingWeights menghitung ulang report_score turunan', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture(50, 50, 70);
    $service = app(ReportScoreLedgerService::class);

    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'P', 80);
    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_KETERAMPILAN, 'K', 60);
    expect(draftReportScore($fixture)->refresh()->final_score)->toBe(70);

    $offering = SubjectOffering::query()->findOrFail($fixture['offering_id']);
    $service->updateOfferingWeights($user, $offering, 80, 20, 75);

    // pen 80 w80, ket 60 w20 => (80*80 + 60*20)/100 = 76 ; KKM 75 => C
    expect(draftReportScore($fixture)->refresh()->final_score)->toBe(76)
        ->and(draftReportScore($fixture)->refresh()->letter_grade)->toBe('C');
});

test('ledger menolak perubahan saat status bukan DRAFT', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture();
    $service = app(ReportScoreLedgerService::class);

    $reportScore = draftReportScore($fixture);
    $reportScore->update(['status' => 'LOCKED']);

    expect(fn () => $service->addItem(
        $user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'X', 90
    ))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('report_score_items', 0);
});

test('ledger menolak skor di luar rentang dan tipe tidak valid', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture();
    $service = app(ReportScoreLedgerService::class);

    expect(fn () => $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'X', 120))
        ->toThrow(InvalidArgumentException::class, 'Nilai harus antara 0 sampai 100.')
        ->and(fn () => $service->addItem($user, draftReportScore($fixture), 'SIKAP', 'X', 80))
        ->toThrow(InvalidArgumentException::class, 'Tipe item nilai tidak valid.')
        ->and(fn () => $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, '  ', 80))
        ->toThrow(InvalidArgumentException::class, 'Label item nilai wajib diisi.');
});

test('setiap operasi ledger tercatat di audit log', function () {
    $user = User::factory()->create();
    $fixture = makeLedgerFixture();
    $service = app(ReportScoreLedgerService::class);

    $service->addItem($user, draftReportScore($fixture), ReportScoreItem::TYPE_PENGETAHUAN, 'UH', 85);

    $this->assertDatabaseHas('audit_logs', [
        'resource_type' => ReportScore::class,
        'resource_id' => $fixture['report_score_id'],
        'action' => 'LEDGER_ADD_ITEM',
        'user_id' => $user->id,
    ]);

    expect(AuditLog::query()->where('action', 'LEDGER_ADD_ITEM')->first()->after_data['ledger']['label'])
        ->toBe('UH');
});
