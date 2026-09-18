<?php

use App\Models\Student;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    public ?int $semesterId = null;

    public array $semesterOptions = [];

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student->load([
            'reportScores.subjectOffering.curriculumSubject.subject',
            'reportScores.subjectOffering.semester.academicYear',
        ]);

        $this->semesterOptions = $this->student->reportScores
            ->map(fn ($score) => $score->subjectOffering?->semester)
            ->filter()
            ->unique('id')
            ->sortBy([
                fn ($a, $b) =>
                    ($a->academic_year_id ?? 0)
                    <=>
                    ($b->academic_year_id ?? 0),

                fn ($a, $b) =>
                    ($a->number ?? 0)
                    <=>
                    ($b->number ?? 0),
            ])
            ->mapWithKeys(function ($semester) {
                return [
                    $semester->id =>
                        ($semester->academicYear?->name ?? '-')
                        . ' — '
                        . ($semester->name ?? '-'),
                ];
            })
            ->all();

        $this->semesterId = collect($this->semesterOptions)
            ->keys()
            ->last();
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">
                Nilai Rapor
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $student->full_name ?? '-' }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            @can('create', App\Models\ReportScore::class)
                <a
                    href="{{ route('report-scores.create', [
                        'student' => $student,
                        'semester' => $semesterId,
                    ]) }}"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Tambah Nilai
                </a>
            @endcan

            <a
                href="{{ route('students.show', $student) }}"
                class="text-sm text-blue-600 hover:underline"
            >
                Kembali ke Siswa
            </a>
        </div>
    </div>

    @if (! empty($semesterOptions))
        <div class="mt-6 max-w-md">
            <label class="block text-sm font-medium">
                Semester
            </label>

            <select
                wire:model.live="semesterId"
                class="mt-1 w-full rounded-lg border px-3 py-2"
            >
                @foreach ($semesterOptions as $id => $label)
                    <option value="{{ $id }}">
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="mt-6 rounded-lg border bg-white p-6">
        @if ($student->reportScores->isEmpty())
            <div class="text-sm text-gray-500">
                Belum ada nilai rapor untuk siswa ini.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="px-3 py-3">Tahun Ajaran</th>
                            <th class="px-3 py-3">Semester</th>
                            <th class="px-3 py-3">Mata Pelajaran</th>
                            <th class="px-3 py-3">Nilai</th>
                            <th class="px-3 py-3">Huruf</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach (
                            $student->reportScores
                                ->when(
                                    $semesterId,
                                    fn ($scores) => $scores->filter(
                                        fn ($score) =>
                                            $score->subjectOffering?->semester_id == $semesterId
                                    )
                                )
                                ->sortBy([
                                    fn ($a, $b) =>
                                        ($a->subjectOffering?->semester?->academic_year_id ?? 0)
                                        <=>
                                        ($b->subjectOffering?->semester?->academic_year_id ?? 0),

                                    fn ($a, $b) =>
                                        ($a->subjectOffering?->semester?->number ?? 0)
                                        <=>
                                        ($b->subjectOffering?->semester?->number ?? 0),

                                    fn ($a, $b) =>
                                        strcmp(
                                            $a->subjectOffering?->curriculumSubject?->subject?->name ?? '',
                                            $b->subjectOffering?->curriculumSubject?->subject?->name ?? ''
                                        ),
                                ])
                            as $score
                        )
                            <tr class="border-b">
                                <td class="px-3 py-3">
                                    {{ $score->subjectOffering?->semester?->academicYear?->name ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $score->subjectOffering?->semester?->name ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $score->subjectOffering?->curriculumSubject?->subject?->name ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $score->final_score ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $score->letter_grade ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $score->status }}
                                </td>

                                <td class="px-3 py-3">
                                    @can('view', $score)
                                        <a
                                            href="{{ route('report-scores.edit', $score) }}"
                                            class="text-blue-600 hover:underline"
                                        >
                                            Lihat
                                        </a>
                                    @else
                                        <span>-</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>