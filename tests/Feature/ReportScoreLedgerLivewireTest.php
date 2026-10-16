<?php

use App\Models\AcademicYear;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\ReportScore;
use App\Models\ReportScoreItem;
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

function ledgerPageFixture(): array
{
    $year = AcademicYear::create(['name' => '2099/2100', 'start_date' => '2099-07-01', 'end_date' => '2100-06-30', 'status' => 'ACTIVE']);
    $semester = Semester::create(['academic_year_id' => $year->id, 'number' => 1, 'name' => 'Semester 1', 'status' => 'ACTIVE']);
    $competency = Competency::create(['code' => 'LGR-RPL', 'name' => 'RPL', 'short_name' => 'RPL', 'is_active' => true]);
    $rombel = Rombel::create(['academic_year_id' => $year->id, 'competency_id' => $competency->id, 'grade' => 10, 'name' => 'X RPL Ledger', 'is_active' => true]);
    $curriculum = Curriculum::create(['name' => 'Kurikulum Ledger', 'version' => '2099', 'is_active' => true]);
    $subject = Subject::create(['code' => 'LGR-WEB', 'name' => 'Pemrograman Web', 'category' => 'GENERAL', 'is_active' => true]);
    $curriculumSubject = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);
    $offering = SubjectOffering::create([
        'curriculum_subject_id' => $curriculumSubject->id, 'rombel_id' => $rombel->id,
        'semester_id' => $semester->id, 'pengetahuan_weight' => 60, 'keterampilan_weight' => 40,
        'kkm' => 75, 'is_active' => true,
    ]);
    $student = Student::create(['nis' => 'LGR-001', 'full_name' => 'Siswa Ledger Page', 'gender' => 'L', 'entry_year' => 2099, 'status' => 'AKTIF']);
    $reportScore = ReportScore::create([
        'student_id' => $student->id, 'subject_offering_id' => $offering->id, 'status' => 'DRAFT',
    ]);

    return compact('student', 'offering', 'reportScore');
}

function ledgerPageUser(string $roleCode): User
{
    return User::factory()->create([
        'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
    ]);
}

test('admin dapat menambah item ledger dan nilai akhir dihitung ulang', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = ledgerPageFixture();
    $admin = ledgerPageUser('ADMIN');

    $page = Livewire::actingAs($admin)
        ->test('pages.report-scores.ledger', ['reportScore' => $fixture['reportScore']])
        ->set('newPenLabel', 'UH 1')
        ->set('newPenScore', 80)
        ->call('addPen')
        ->set('newKetLabel', 'Praktikum')
        ->set('newKetScore', 70)
        ->call('addKet');

    $reportScore = $fixture['reportScore']->refresh();

    // (80*60 + 70*40)/100 = 76 ; KKM 75 => C
    expect($reportScore->final_score)->toBe(76)
        ->and($reportScore->letter_grade)->toBe('C')
        ->and(ReportScoreItem::query()->where('report_score_id', $reportScore->id)->count())->toBe(2);

    $page->assertSee('76')
        ->assertSee('C');
});

test('perubahan bobot dan KKM memicu hitung ulang', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = ledgerPageFixture();
    $admin = ledgerPageUser('ADMIN');

    Livewire::actingAs($admin)
        ->test('pages.report-scores.ledger', ['reportScore' => $fixture['reportScore']])
        ->set('newPenLabel', 'P')->set('newPenScore', 100)->call('addPen')
        ->set('newKetLabel', 'K')->set('newKetScore', 60)->call('addKet')
        ->set('penBobot', 100)->set('ketBobot', 0)->set('kkm', 90)
        ->call('saveWeights');

    $reportScore = $fixture['reportScore']->refresh();

    // pen 100 w100, ket 60 w0 => 100 ; KKM 90 => A
    expect($reportScore->final_score)->toBe(100)
        ->and($reportScore->letter_grade)->toBe('A');
});

test('nilai terkunci tidak menampilkan form ledger', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $fixture = ledgerPageFixture();
    $fixture['reportScore']->update(['status' => 'LOCKED']);
    $admin = ledgerPageUser('ADMIN');

    Livewire::actingAs($admin)
        ->test('pages.report-scores.ledger', ['reportScore' => $fixture['reportScore']])
        ->assertSee('LOCKED')
        ->assertDontSeeHtml('wire:click="addPen"');
});
