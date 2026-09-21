<?php

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Services\ReportScoreWorkflowService;
use Livewire\Component;

new class extends Component
{
    public ReportScore $reportScore;

    public ?int $finalScore = null;

    public ?string $letterGrade = null;

    public ?string $description = null;

    public ?int $correctionFinalScore = null;

    public ?string $correctionLetterGrade = null;

    public ?string $correctionDescription = null;

    public string $correctionReason = '';

    public array $auditLogs = [];

    public function mount(ReportScore $reportScore): void
    {
        $this->authorize('view', $reportScore);

        $this->reportScore = $reportScore->load([
            'student',
            'subjectOffering.curriculumSubject.subject',
            'subjectOffering.semester.academicYear',
        ]);

        $this->finalScore = $reportScore->final_score;
        $this->letterGrade = $reportScore->letter_grade;
        $this->description = $reportScore->description;

        $this->correctionFinalScore = $reportScore->final_score;
        $this->correctionLetterGrade = $reportScore->letter_grade;
        $this->correctionDescription = $reportScore->description;
        $this->loadAuditLogs();
    }

    public function loadAuditLogs(): void
    {
        $this->auditLogs = AuditLog::query()
            ->with('user')
            ->where('resource_type', ReportScore::class)
            ->where('resource_id', $this->reportScore->id)
            ->latest('id')
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user?->name ?? '-',
                    'before_data' => $log->before_data,
                    'after_data' => $log->after_data,
                    'created_at' => $log->created_at?->format('d-m-Y H:i'),
                ];
            })
            ->all();
    }

    public function save(): void
    {
        $this->authorize('update', $this->reportScore);

        $validated = $this->validate([
            'finalScore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'letterGrade' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ]);

        $before = [
            'final_score' => $this->reportScore->final_score,
            'letter_grade' => $this->reportScore->letter_grade,
            'description' => $this->reportScore->description,
            'status' => $this->reportScore->status,
        ];

        $this->reportScore->update([
            'final_score' => $validated['finalScore'],
            'letter_grade' => $validated['letterGrade'],
            'description' => $validated['description'],
        ]);

        $after = [
            'final_score' => $this->reportScore->final_score,
            'letter_grade' => $this->reportScore->letter_grade,
            'description' => $this->reportScore->description,
            'status' => $this->reportScore->status,
        ];

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'UPDATE_REPORT_SCORE',
            'resource_type' => ReportScore::class,
            'resource_id' => $this->reportScore->id,
            'before_data' => $before,
            'after_data' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->loadAuditLogs();

        session()->flash('success', 'Nilai rapor berhasil disimpan.');
    }

    public function lockScore(): void
    {
        app(ReportScoreWorkflowService::class)
            ->lock(auth()->user(), $this->reportScore);

        $this->reportScore->refresh();

        $this->loadAuditLogs();

        session()->flash('success', 'Nilai rapor berhasil dikunci.');
    }

    public function finalizeScore(): void
    {
        app(ReportScoreWorkflowService::class)
            ->finalize(auth()->user(), $this->reportScore);

        $this->reportScore->refresh();

        $this->loadAuditLogs();

        $this->correctionFinalScore = $this->reportScore->final_score;
        $this->correctionLetterGrade = $this->reportScore->letter_grade;
        $this->correctionDescription = $this->reportScore->description;

        session()->flash('success', 'Nilai rapor berhasil difinalisasi.');
    }

    public function correctScore(): void
    {
        $this->authorize('correct', $this->reportScore);

        $validated = $this->validate([
            'correctionFinalScore' => ['required', 'integer', 'min:0', 'max:100'],
            'correctionLetterGrade' => ['nullable', 'string', 'max:10'],
            'correctionDescription' => ['nullable', 'string'],
            'correctionReason' => ['required', 'string', 'min:5'],
        ]);

        app(ReportScoreWorkflowService::class)->correct(
            auth()->user(),
            $this->reportScore,
            $validated['correctionFinalScore'],
            $validated['correctionLetterGrade'],
            $validated['correctionDescription'],
            $validated['correctionReason'],
        );

        $this->reportScore->refresh();

        $this->loadAuditLogs();

        $this->finalScore = $this->reportScore->final_score;
        $this->letterGrade = $this->reportScore->letter_grade;
        $this->description = $this->reportScore->description;

        $this->correctionFinalScore = $this->reportScore->final_score;
        $this->correctionLetterGrade = $this->reportScore->letter_grade;
        $this->correctionDescription = $this->reportScore->description;
        $this->correctionReason = '';

        session()->flash('success', 'Nilai final berhasil dikoreksi.');
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">TES EDIT NILAI 123</h1>

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

    @if (session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

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

        <div class="mt-4 rounded-lg border p-3 text-sm">
            Debug user:
            {{ auth()->user()?->email ?? '-' }}
            |
            Role:
            {{ auth()->user()?->role?->code ?? '-' }}
            |
            Can correct:
            {{ auth()->user()?->can('correct', $reportScore) ? 'YES' : 'NO' }}
        </div>

        @if ($reportScore->status === 'DRAFT')
            @can('update', $reportScore)
                <form wire:submit="save" class="mt-6 space-y-4">
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

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white"
                        >
                            Simpan
                        </button>

                        @can('lock', $reportScore)
                            <button
                                type="button"
                                wire:click="lockScore"
                                wire:confirm="Yakin ingin mengunci nilai ini?"
                                class="rounded-lg bg-amber-600 px-4 py-2 font-medium text-white"
                            >
                                Kunci Nilai
                            </button>
                        @endcan
                    </div>
                </form>

            @else
                <div class="mt-6 rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-600">
                        Nilai masih berstatus draft dan hanya dapat dilihat.
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div>
                            <div class="text-sm text-gray-500">Nilai Akhir</div>
                            <div class="mt-1 font-medium">
                                {{ $reportScore->final_score ?? '-' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">Nilai Huruf</div>
                            <div class="mt-1 font-medium">
                                {{ $reportScore->letter_grade ?? '-' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">Deskripsi</div>
                            <div class="mt-1 font-medium">
                                {{ $reportScore->description ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endcan

        @elseif ($reportScore->status === 'LOCKED')
            <div class="mt-6">
                <p class="text-sm text-gray-600">
                    Nilai sudah dikunci dan tidak dapat diedit.
                </p>

                @can('finalize', $reportScore)
                    <button
                        type="button"
                        wire:click="finalizeScore"
                        wire:confirm="Yakin ingin memfinalisasi nilai ini?"
                        class="mt-4 rounded-lg bg-green-600 px-4 py-2 font-medium text-white"
                    >
                        Finalisasi Nilai
                    </button>
                @endcan
            </div>

        @elseif ($reportScore->status === 'FINALIZED')
            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">
                Nilai sudah difinalisasi dan tidak dapat diedit melalui form biasa.
            </div>

            @can('correct', $reportScore)
                <form wire:submit="correctScore" class="mt-6 space-y-4">
                    <div class="border-t pt-6">
                        <h2 class="text-lg font-semibold">
                            Koreksi Nilai Final
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Setiap koreksi nilai final akan dicatat di audit log.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">
                            Nilai Akhir
                        </label>

                        <input
                            type="number"
                            min="0"
                            max="100"
                            wire:model="correctionFinalScore"
                            class="mt-1 w-full rounded-lg border px-3 py-2"
                        >

                        @error('correctionFinalScore')
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
                            wire:model="correctionLetterGrade"
                            class="mt-1 w-full rounded-lg border px-3 py-2"
                        >

                        @error('correctionLetterGrade')
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
                            wire:model="correctionDescription"
                            rows="4"
                            class="mt-1 w-full rounded-lg border px-3 py-2"
                        ></textarea>

                        @error('correctionDescription')
                            <div class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">
                            Alasan Koreksi
                        </label>

                        <textarea
                            wire:model="correctionReason"
                            rows="3"
                            class="mt-1 w-full rounded-lg border px-3 py-2"
                            placeholder="Contoh: Kesalahan input nilai saat entri awal."
                        ></textarea>

                        @error('correctionReason')
                            <div class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        wire:confirm="Yakin ingin mengoreksi nilai yang sudah difinalisasi?"
                        class="rounded-lg bg-red-600 px-4 py-2 font-medium text-white"
                    >
                        Simpan Koreksi
                    </button>
                </form>
            @endcan
        @endif
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <div>
            <h2 class="text-lg font-semibold">
                Riwayat Perubahan Nilai
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Riwayat aktivitas yang berkaitan dengan nilai rapor ini.
            </p>
        </div>

        @if (empty($auditLogs))
            <div class="mt-4 text-sm text-gray-500">
                Belum ada riwayat perubahan.
            </div>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="px-3 py-3">Waktu</th>
                            <th class="px-3 py-3">Pengguna</th>
                            <th class="px-3 py-3">Aktivitas</th>
                            <th class="px-3 py-3">Nilai Sebelum</th>
                            <th class="px-3 py-3">Nilai Sesudah</th>
                            <th class="px-3 py-3">Alasan Koreksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($auditLogs as $log)
                            @php
                                $beforeScore = data_get(
                                    $log,
                                    'before_data.final_score'
                                );

                                $afterScore = data_get(
                                    $log,
                                    'after_data.final_score'
                                );

                                $correctionReason = data_get(
                                    $log,
                                    'after_data.correction_reason'
                                );

                                $actionLabel = match ($log['action']) {
                                    'CREATE_REPORT_SCORE' => 'Membuat Nilai',
                                    'UPDATE_REPORT_SCORE' => 'Mengubah Nilai',
                                    'LOCK_REPORT_SCORE' => 'Mengunci Nilai',
                                    'FINALIZE_REPORT_SCORE' => 'Finalisasi Nilai',
                                    'CORRECT_FINALIZED_REPORT_SCORE' => 'Koreksi Nilai Final',
                                    default => $log['action'],
                                };
                            @endphp

                            <tr class="border-b align-top">
                                <td class="px-3 py-3 whitespace-nowrap">
                                    {{ $log['created_at'] ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $log['user_name'] ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $actionLabel }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $beforeScore ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $afterScore ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $correctionReason ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>