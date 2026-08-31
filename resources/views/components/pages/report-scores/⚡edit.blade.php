<?php

use App\Models\ReportScore;
use Livewire\Component;

new class extends Component
{
    public ReportScore $reportScore;

    public ?int $finalScore = null;

    public ?string $letterGrade = null;

    public ?string $description = null;

    public function mount(ReportScore $reportScore): void
    {
        $this->authorize('update', $reportScore);

        $this->reportScore = $reportScore->load([
            'student',
            'subjectOffering.curriculumSubject.subject',
            'subjectOffering.semester.academicYear',
        ]);

        $this->finalScore = $reportScore->final_score;
        $this->letterGrade = $reportScore->letter_grade;
        $this->description = $reportScore->description;
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Edit Nilai Rapor</h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $reportScore->student?->full_name ?? '-' }}
            </p>
        </div>

        <a
            href="{{ route('students.show', $reportScore->student_id) }}"
            class="text-sm text-blue-600 hover:underline"
        >
            Kembali
        </a>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <div class="text-sm text-gray-500">Mata Pelajaran</div>
                <div class="mt-1 font-medium">
                    {{ $reportScore->subjectOffering?->curriculumSubject?->subject?->name ?? '-' }}
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500">Semester</div>
                <div class="mt-1 font-medium">
                    {{ $reportScore->subjectOffering?->semester?->academicYear?->name ?? '-' }}
                    —
                    {{ $reportScore->subjectOffering?->semester?->name ?? '-' }}
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500">Status</div>
                <div class="mt-1 font-medium">
                    {{ $reportScore->status }}
                </div>
            </div>
        </div>

        <form class="mt-6 space-y-4">
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
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Nilai Huruf
                </label>

                <input
                    type="text"
                    wire:model="letterGrade"
                    class="mt-1 w-full rounded-lg border px-3 py-2"
                >
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
            </div>

            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-white"
            >
                Simpan
            </button>
        </form>
    </div>
</div>