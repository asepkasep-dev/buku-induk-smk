<?php

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\Student;
use App\Models\SubjectOffering;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    public ?int $subjectOfferingId = null;

    public ?int $finalScore = null;

    public ?string $letterGrade = null;

    public ?string $description = null;

    public array $offeringOptions = [];

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->authorize('create', ReportScore::class);

        $this->student = $student;

        $enrollment = $student->enrollments()
            ->where('status', 'ACTIVE')
            ->latest('id')
            ->first();

        if (! $enrollment) {
            abort(422, 'Siswa belum memiliki enrollment aktif.');
        }

        $this->offeringOptions = SubjectOffering::query()
            ->with([
                'curriculumSubject.subject',
                'semester.academicYear',
                'rombel',
            ])
            ->where('is_active', true)
            ->where('rombel_id', $enrollment->rombel_id)
            ->whereNotIn(
                'id',
                ReportScore::query()
                    ->where('student_id', $student->id)
                    ->select('subject_offering_id')
            )
            ->whereHas('semester', function ($query) use ($enrollment) {
                $query->where(
                    'academic_year_id',
                    $enrollment->academic_year_id
                );
            })
            ->get()
            ->mapWithKeys(function (SubjectOffering $offering) {
                $academicYear =
                    $offering->semester?->academicYear?->name ?? '-';

                $semester =
                    $offering->semester?->name ?? '-';

                $rombel =
                    $offering->rombel?->name ?? '-';

                $subject =
                    $offering->curriculumSubject?->subject?->name ?? '-';

                return [
                    $offering->id =>
                        "{$academicYear} — {$semester} — {$rombel} — {$subject}",
                ];
            })
            ->all();
    }

    public function save()
    {
        $this->authorize('create', ReportScore::class);

        $validated = $this->validate([
            'subjectOfferingId' => [
                'required',
                'integer',
                'exists:subject_offerings,id',
            ],
            'finalScore' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'letterGrade' => [
                'nullable',
                'string',
                'max:10',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $enrollment = $this->student->enrollments()
            ->where('status', 'ACTIVE')
            ->latest('id')
            ->first();

        if (! $enrollment) {
            $this->addError(
                'subjectOfferingId',
                'Siswa tidak memiliki enrollment aktif.'
            );

            return;
        }

        $offering = SubjectOffering::query()
            ->whereKey($validated['subjectOfferingId'])
            ->where('is_active', true)
            ->where('rombel_id', $enrollment->rombel_id)
            ->whereHas('semester', function ($query) use ($enrollment) {
                $query->where(
                    'academic_year_id',
                    $enrollment->academic_year_id
                );
            })
            ->first();

        if (! $offering) {
            $this->addError(
                'subjectOfferingId',
                'Mata pelajaran tidak sesuai dengan rombel aktif siswa.'
            );

            return;
        }

        $alreadyExists = ReportScore::query()
            ->where('student_id', $this->student->id)
            ->where('subject_offering_id', $offering->id)
            ->exists();

        if ($alreadyExists) {
            $this->addError(
                'subjectOfferingId',
                'Nilai untuk mata pelajaran dan semester ini sudah ada.'
            );

            return;
        }

        $reportScore = ReportScore::create([
            'student_id' => $this->student->id,
            'subject_offering_id' => $offering->id,
            'final_score' => $validated['finalScore'],
            'letter_grade' => $validated['letterGrade'],
            'description' => $validated['description'],
            'status' => 'DRAFT',
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'CREATE_REPORT_SCORE',
            'resource_type' => ReportScore::class,
            'resource_id' => $reportScore->id,
            'before_data' => null,
            'after_data' => $reportScore->fresh()->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        session()->flash(
            'success',
            'Nilai rapor berhasil dibuat sebagai DRAFT.'
        );

        return $this->redirect(
            route('report-scores.edit', $reportScore)
        );
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">
                Tambah Nilai Rapor
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $student->full_name ?? '-' }}
            </p>
        </div>

        <a
            href="{{ route('report-scores.index', $student) }}"
            class="text-sm text-blue-600 hover:underline"
        >
            Kembali
        </a>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        @if (empty($offeringOptions))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                <h2 class="font-medium text-green-800">
                    Semua mata pelajaran sudah memiliki nilai.
                </h2>

                <p class="mt-1 text-sm text-green-700">
                    Tidak ada mata pelajaran aktif yang masih perlu ditambahkan untuk siswa ini.
                </p>

                <a
                    href="{{ route('report-scores.index', $student) }}"
                    class="mt-4 inline-block text-sm font-medium text-blue-600 hover:underline"
                >
                    Kembali ke Daftar Nilai
                </a>
            </div>
        @else
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium">
                    Mata Pelajaran
                </label>

                <select
                    wire:model="subjectOfferingId"
                    class="mt-1 w-full rounded-lg border px-3 py-2"
                >
                    <option value="">
                        @if (empty($offeringOptions))
                            <option value="">
                                -- Tidak ada mata pelajaran yang belum dinilai --
                            </option>
                        @else
                            <option value="">
                                -- Pilih Mata Pelajaran --
                            </option>
                        @endif
                    </option>

                    @foreach ($offeringOptions as $id => $label)
                        <option value="{{ $id }}">
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                @error('subjectOfferingId')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Nilai Akhir
                </label>

                <input
                    type="number"
                    min="0"
                    max="100"
                    wire:model="finalScore"
                    class="mt-1 w-full rounded-lg border px-3 py-2"
                >

                @error('finalScore')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Nilai Huruf
                </label>

                <input
                    type="text"
                    wire:model="letterGrade"
                    class="mt-1 w-full rounded-lg border px-3 py-2"
                    placeholder="Contoh: A"
                >

                @error('letterGrade')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Deskripsi
                </label>

                <textarea
                    wire:model="description"
                    rows="4"
                    class="mt-1 w-full rounded-lg border px-3 py-2"
                ></textarea>

                @error('description')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <button
                type="submit"
                @disabled(empty($offeringOptions))
                class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
            >
                Simpan Nilai
            </button>
        </form>
    @endif
</div>