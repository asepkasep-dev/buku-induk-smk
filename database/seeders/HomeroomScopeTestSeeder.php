<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassAssignment;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\ReportScore;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubjectOffering;

class HomeroomScopeTestSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'status' => 'ACTIVE',
            ]
        );

        $competency = Competency::where('code', 'RPL')->firstOrFail();

        // Rombel yang menjadi tanggung jawab wali kelas
        $rombel = Rombel::updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'X RPL 1',
            ],
            [
                'competency_id' => $competency->id,
                'grade' => 10,
                'is_active' => true,
            ]
        );

        // User wali kelas
        $wali = User::updateOrCreate(
            ['email' => 'waliuji@example.com'],
            [
                'name' => 'Wali Kelas Uji',
                'password' => Hash::make('password'),
                'role_id' => Role::where('code', 'WALI_KELAS')->value('id'),
            ]
        );

        // Penugasan wali kelas ke X RPL 1
        ClassAssignment::updateOrCreate(
            [
                'user_id' => $wali->id,
                'rombel_id' => $rombel->id,
                'academic_year_id' => $academicYear->id,
                'status' => 'ACTIVE',
            ],
            [
                'start_date' => '2026-07-01',
            ]
        );

        // Siswa yang berada di rombel wali kelas
        $student = Student::updateOrCreate(
            ['nis' => 'TEST001'],
            [
                'full_name' => 'Siswa Scope Uji',
                'gender' => 'L',
                'entry_year' => 2026,
                'status' => 'AKTIF',
            ]
        );

        Enrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'status' => 'ACTIVE',
            ],
            [
                'rombel_id' => $rombel->id,
                'start_date' => '2026-07-01',
            ]
        );

        // Rombel lain yang bukan tanggung jawab wali kelas
        $otherRombel = Rombel::updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'X RPL 2',
            ],
            [
                'competency_id' => $competency->id,
                'grade' => 10,
                'is_active' => true,
            ]
        );

        // Siswa di rombel lain
        $otherStudent = Student::updateOrCreate(
            ['nis' => 'TEST002'],
            [
                'full_name' => 'Siswa Rombel Lain',
                'gender' => 'L',
                'entry_year' => 2026,
                'status' => 'AKTIF',
            ]
        );

        Enrollment::updateOrCreate(
            [
                'student_id' => $otherStudent->id,
                'academic_year_id' => $academicYear->id,
                'status' => 'ACTIVE',
            ],
            [
                'rombel_id' => $otherRombel->id,
                'start_date' => '2026-07-01',
            ]
        );
        $studentUser = User::updateOrCreate(
            ['email' => 'siswauji@example.com'],
            [
                'name' => 'Akun Siswa Uji',
                'password' => Hash::make('password'),
                'role_id' => Role::where('code', 'STUDENT')->value('id'),
                'student_id' => $student->id,
            ]
        );
        $operator = User::updateOrCreate(
            ['email' => 'operatoruji@example.com'],
            [
                'name' => 'Operator Uji',
                'password' => Hash::make('password'),
                'role_id' => Role::where('code', 'OPERATOR')->value('id'),
            ]
        );

        \App\Models\OperatorRombelScope::updateOrCreate(
            [
                'user_id' => $operator->id,
                'rombel_id' => $rombel->id,
            ],
            [
                'is_active' => true,
            ]
        );

        $semester = Semester::updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'number' => 1,
            ],
            [
                'name' => 'Semester 1',
                'status' => 'ACTIVE',
            ]
        );

        $curriculum = Curriculum::firstOrCreate(
            [
                'name' => 'Kurikulum Merdeka Uji',
                'version' => '2026',
            ],
            [
                'is_active' => true,
            ]
        );

        $subject = Subject::updateOrCreate(
            ['code' => 'TEST-MTK'],
            [
                'name' => 'Matematika Uji',
                'is_active' => true,
            ]
        );

        $curriculumSubject = CurriculumSubject::updateOrCreate(
            [
                'curriculum_id' => $curriculum->id,
                'subject_id' => $subject->id,
                'competency_id' => null,
                'grade' => 10,
                'semester_number' => 1,
            ],
            [
                'is_active' => true,
            ]
        );

        $subjectOffering = SubjectOffering::updateOrCreate(
            [
                'curriculum_subject_id' => $curriculumSubject->id,
                'rombel_id' => $rombel->id,
                'semester_id' => $semester->id,
            ],
            [
                'is_active' => true,
            ]
        );

        ReportScore::updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_offering_id' => $subjectOffering->id,
            ],
            [
                'final_score' => 85,
                'letter_grade' => 'B',
                'description' => 'Nilai uji scope dan policy.',
                'status' => 'DRAFT',
            ]
        );
    }
}