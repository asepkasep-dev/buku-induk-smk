<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceSummary;
use App\Models\ClassAssignment;
use App\Models\DiplomaScore;
use App\Models\Enrollment;
use App\Models\ExtracurricularRecord;
use App\Models\PKLRecord;
use App\Models\ReportScore;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubjectOffering;
use App\Models\UKKRecord;

class ReportCardAssembler
{
    public function __construct(
        private GradePredicateResolver $resolver,
    ) {}

    /**
     * Menyusun seluruh data rapor siswa untuk semester tertentu.
     *
     * @return array<string, mixed>
     */
    public function assemble(Student $student, Semester $semester): array
    {
        $school = School::query()->first();
        $academicYear = $semester->academicYear ?? AcademicYear::query()->find($semester->academic_year_id);

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $semester->academic_year_id)
            ->where('status', 'ACTIVE')
            ->with(['rombel.competency'])
            ->first();

        $rombel = $enrollment?->rombel;

        $offerings = $rombel
            ? SubjectOffering::query()
                ->where('rombel_id', $rombel->id)
                ->where('semester_id', $semester->id)
                ->where('is_active', true)
                ->with(['curriculumSubject.subject'])
                ->get()
            : collect();

        $scores = ReportScore::query()
            ->where('student_id', $student->id)
            ->whereIn('subject_offering_id', $offerings->pluck('id'))
            ->get()
            ->keyBy('subject_offering_id');

        $subjects = $offerings->map(function (SubjectOffering $offering) use ($scores) {
            $curriculumSubject = $offering->curriculumSubject;
            $subject = $curriculumSubject?->subject;
            $score = $scores->get($offering->id);

            $kkm = (int) ($offering->kkm ?? GradePredicateResolver::DEFAULT_KKM);
            $final = $score?->final_score;
            $resolved = $this->resolver->resolve($final !== null ? (float) $final : null, $kkm);

            $category = $subject?->category ?? 'GENERAL';
            $categoryLabel = match ($category) {
                'VOCATIONAL' => 'Muatan Peminatan Kejuruan',
                'LOCAL' => 'Muatan Lokal',
                default => 'Muatan Nasional & Kewilayahan',
            };

            return [
                'offering_id' => $offering->id,
                'subject_id' => $subject?->id,
                'subject_code' => $subject?->code ?? '-',
                'subject_name' => $subject?->name ?? ($curriculumSubject?->name ?? 'Mata Pelajaran'),
                'category' => $category,
                'category_label' => $categoryLabel,
                'kkm' => $kkm,
                'pengetahuan' => $score?->pengetahuan_avg,
                'keterampilan' => $score?->keterampilan_avg,
                'final_score' => $final,
                'letter' => $score?->letter_grade ?? $resolved['letter'] ?? '-',
                'predicate' => $resolved['predicate'] ?? '-',
                'tuntas' => $resolved['tuntas'],
                'description' => $score?->description ?? '-',
                'status' => $score?->status ?? 'BELUM_DIISI',
            ];
        })->sortBy([
            fn ($a, $b) => strcmp($a['category'], $b['category']),
            fn ($a, $b) => strcmp($a['subject_name'], $b['subject_name']),
        ])->values();

        $attendance = AttendanceSummary::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->first();

        $extracurriculars = ExtracurricularRecord::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->with('extracurricular')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->extracurricular?->name ?? '-',
                'predicate' => $r->predicate ?? '-',
                'description' => $r->description ?? '-',
            ])->all();

        $pkl = PKLRecord::query()
            ->where('student_id', $student->id)
            ->get()
            ->map(fn ($p) => [
                'partner' => $p->partner_name,
                'location' => $p->location ?? '-',
                'duration' => $p->start_date && $p->end_date
                    ? $p->start_date->format('d/m/Y').' - '.$p->end_date->format('d/m/Y')
                    : '-',
                'competency_score' => $p->competency_score,
                'competency_predicate' => $p->competency_predicate ?? '-',
                'attitude_score' => $p->attitude_score,
                'attitude_predicate' => $p->attitude_predicate ?? '-',
                'description' => $p->description ?? '-',
            ])->all();

        $ukk = UKKRecord::query()
            ->where('student_id', $student->id)
            ->with('competency')
            ->get()
            ->map(fn ($u) => [
                'competency' => $u->competency?->name ?? '-',
                'score' => $u->final_score,
                'predicate' => $u->predicate ?? '-',
            ])->all();

        $diplomaScores = DiplomaScore::query()
            ->where('student_id', $student->id)
            ->with('subject')
            ->get()
            ->map(fn ($d) => [
                'subject' => $d->subject?->name ?? '-',
                'score' => $d->final_score,
                'predicate' => $d->predicate ?? '-',
            ])->all();

        $homeroomAssignment = $rombel
            ? ClassAssignment::query()
                ->where('rombel_id', $rombel->id)
                ->where('academic_year_id', $semester->academic_year_id)
                ->where('status', 'ACTIVE')
                ->with('user')
                ->first()
            : null;

        $homeroomUser = $homeroomAssignment?->user;

        $logoDataUri = $this->resolveLogoDataUri($school?->logo_path);

        $graded = $subjects->whereNotNull('final_score');
        $averageFinal = $graded->isNotEmpty() ? round($graded->avg('final_score'), 1) : null;

        return [
            'school' => [
                'name' => $school?->name ?? 'SMK Negeri',
                'npsn' => $school?->npsn ?? '-',
                'nss' => $school?->nss ?? '-',
                'address' => $school?->address ?? '-',
                'village' => $school?->village,
                'district' => $school?->district,
                'city' => $school?->city ?? 'Kota',
                'province' => $school?->province,
                'postal_code' => $school?->postal_code,
                'phone' => $school?->phone ?? '-',
                'email' => $school?->email ?? '-',
                'website' => $school?->website ?? '-',
                'principal_name' => $school?->principal_name ?? 'Kepala Sekolah',
                'principal_nip' => $school?->principal_nip ?? '-',
                'logo_path' => $school?->logo_path,
                'logo_data_uri' => $logoDataUri,
            ],
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn ?? '-',
                'gender' => $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : '-'),
            ],
            'academic' => [
                'year_name' => $academicYear?->name ?? '-',
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'semester_number' => $semester->number,
                'grade' => $rombel?->grade ?? '-',
                'rombel_id' => $rombel?->id,
                'rombel_name' => $rombel?->name ?? '-',
                'competency_name' => $rombel?->competency?->name ?? '-',
                'competency_code' => $rombel?->competency?->code ?? '-',
            ],
            'subjects' => $subjects->all(),
            'subjects_by_category' => $subjects->groupBy('category_label')->all(),
            'attendance' => [
                'sick' => (int) ($attendance?->sick ?? 0),
                'excused' => (int) ($attendance?->excused ?? 0),
                'absent' => (int) ($attendance?->absent ?? 0),
            ],
            'extracurriculars' => $extracurriculars,
            'pkl' => $pkl,
            'ukk' => $ukk,
            'diploma_scores' => $diplomaScores,
            'signatures' => [
                'place_date' => ($school?->city ?? 'Sekolah').', '.$this->formatIndonesianDate(now()),
                'homeroom_name' => $homeroomUser?->name ?? 'Wali Kelas',
                'homeroom_nip' => '-',
                'principal_name' => $school?->principal_name ?? 'Kepala Sekolah',
                'principal_nip' => $school?->principal_nip ?? '-',
            ],
            'summary' => [
                'total_subjects' => $subjects->count(),
                'graded_subjects' => $graded->count(),
                'average_final_score' => $averageFinal,
                'is_complete' => $subjects->isNotEmpty() && $subjects->count() === $graded->count(),
            ],
        ];
    }

    private function resolveLogoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $fullPath = storage_path('app/public/'.$path);
        if (! file_exists($fullPath)) {
            $fullPath = public_path('storage/'.$path);
        }

        if (file_exists($fullPath) && is_readable($fullPath)) {
            $mime = mime_content_type($fullPath) ?: 'image/png';
            $data = base64_encode(file_get_contents($fullPath));

            return 'data:'.$mime.';base64,'.$data;
        }

        return null;
    }

    private function formatIndonesianDate(\DateTimeInterface $date): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $day = $date->format('j');
        $month = (int) $date->format('n');
        $year = $date->format('Y');

        return "{$day} {$months[$month]} {$year}";
    }
}
