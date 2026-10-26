<?php

use App\Models\AcademicYear;
use App\Models\AttendanceSummary;
use App\Models\ClassAssignment;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRecord;
use App\Models\PKLRecord;
use App\Models\ReportScore;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\UKKRecord;
use App\Models\User;
use App\Services\ReportCardAssembler;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ReportCardAssembler menyusun seluruh data rapor siswa secara akurat', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);

    $school = School::create([
        'name' => 'SMK Negeri 1 Uji',
        'npsn' => '12345678',
        'nss' => '87654321',
        'address' => 'Jl. Pendidikan No. 1',
        'city' => 'Bandung',
        'principal_name' => 'Dr. H. Ahmad, M.Pd.',
        'principal_nip' => '197001011995011001',
    ]);

    $year = AcademicYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'status' => 'ACTIVE',
    ]);

    $semester = Semester::create([
        'academic_year_id' => $year->id,
        'number' => 1,
        'name' => 'Semester Ganjil',
        'status' => 'ACTIVE',
    ]);

    $competency = Competency::create([
        'code' => 'RPL-01',
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

    $homeroomUser = User::factory()->create([
        'name' => 'Budi Santoso, S.Kom.',
        'role_id' => Role::where('code', 'WALI_KELAS')->first()->id,
    ]);

    ClassAssignment::create([
        'user_id' => $homeroomUser->id,
        'rombel_id' => $rombel->id,
        'academic_year_id' => $year->id,
        'status' => 'ACTIVE',
    ]);

    $curriculum = Curriculum::create(['name' => 'Kurikulum Merdeka SMK', 'version' => '2026', 'is_active' => true]);
    $subject1 = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'category' => 'GENERAL', 'is_active' => true]);
    $subject2 = Subject::create(['code' => 'PROG', 'name' => 'Pemrograman Web', 'category' => 'VOCATIONAL', 'is_active' => true]);

    $cs1 = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject1->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);
    $cs2 = CurriculumSubject::create(['curriculum_id' => $curriculum->id, 'subject_id' => $subject2->id, 'grade' => 10, 'semester_number' => 1, 'is_active' => true]);

    $offering1 = SubjectOffering::create([
        'curriculum_subject_id' => $cs1->id,
        'rombel_id' => $rombel->id,
        'semester_id' => $semester->id,
        'pengetahuan_weight' => 50,
        'keterampilan_weight' => 50,
        'kkm' => 70,
        'is_active' => true,
    ]);

    $offering2 = SubjectOffering::create([
        'curriculum_subject_id' => $cs2->id,
        'rombel_id' => $rombel->id,
        'semester_id' => $semester->id,
        'pengetahuan_weight' => 40,
        'keterampilan_weight' => 60,
        'kkm' => 75,
        'is_active' => true,
    ]);

    $student = Student::create([
        'nis' => '10001',
        'nisn' => '0071234567',
        'full_name' => 'Raden Muhammad',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'rombel_id' => $rombel->id,
        'academic_year_id' => $year->id,
        'status' => 'ACTIVE',
    ]);

    $score1 = ReportScore::create([
        'student_id' => $student->id,
        'subject_offering_id' => $offering1->id,
        'pengetahuan_avg' => 84.0,
        'keterampilan_avg' => 86.0,
        'final_score' => 85,
        'letter_grade' => 'B',
        'description' => 'Sangat menguasai aljabar.',
        'status' => 'FINALIZED',
    ]);

    AttendanceSummary::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'sick' => 2,
        'excused' => 1,
        'absent' => 0,
        'status' => 'FINALIZED',
    ]);

    $ekskul = Extracurricular::create(['name' => 'Pramuka', 'is_active' => true]);
    ExtracurricularRecord::create([
        'student_id' => $student->id,
        'extracurricular_id' => $ekskul->id,
        'semester_id' => $semester->id,
        'predicate' => 'A',
        'description' => 'Aktif dalam kegiatan kepramukaan.',
        'status' => 'FINALIZED',
    ]);

    PKLRecord::create([
        'student_id' => $student->id,
        'partner_name' => 'PT Teknologi Maju',
        'location' => 'Bandung',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'competency_score' => 90,
        'competency_predicate' => 'A',
        'attitude_score' => 92,
        'attitude_predicate' => 'A',
        'description' => 'Sangat disiplin dan menguasai Git.',
        'status' => 'FINALIZED',
    ]);

    UKKRecord::create([
        'student_id' => $student->id,
        'competency_id' => $competency->id,
        'final_score' => 88,
        'predicate' => 'Sangat Baik',
        'status' => 'FINALIZED',
    ]);

    /** @var ReportCardAssembler $assembler */
    $assembler = app(ReportCardAssembler::class);
    $card = $assembler->assemble($student, $semester);

    expect($card['school']['name'])->toBe('SMK Negeri 1 Uji')
        ->and($card['student']['full_name'])->toBe('Raden Muhammad')
        ->and($card['student']['nis'])->toBe('10001')
        ->and($card['academic']['rombel_name'])->toBe('X RPL 1')
        ->and($card['academic']['competency_name'])->toBe('Rekayasa Perangkat Lunak')
        ->and($card['attendance']['sick'])->toBe(2)
        ->and($card['extracurriculars'][0]['name'])->toBe('Pramuka')
        ->and($card['pkl'][0]['partner'])->toBe('PT Teknologi Maju')
        ->and($card['ukk'][0]['score'])->toBe(88)
        ->and($card['signatures']['homeroom_name'])->toBe('Budi Santoso, S.Kom.')
        ->and($card['signatures']['principal_name'])->toBe('Dr. H. Ahmad, M.Pd.')
        ->and($card['summary']['total_subjects'])->toBe(2)
        ->and($card['summary']['graded_subjects'])->toBe(1);
});
