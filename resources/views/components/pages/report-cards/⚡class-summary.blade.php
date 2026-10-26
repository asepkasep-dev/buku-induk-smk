<?php

use App\Models\Enrollment;
use App\Models\ReportScore;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\SubjectOffering;
use Livewire\Component;

new class extends Component
{
    public Rombel $rombel;

    public Semester $semester;

    public function mount(Rombel $rombel, Semester $semester): void
    {
        $this->authorizeClassAccess($rombel);

        $this->rombel = $rombel->load(['academicYear', 'competency']);
        $this->semester = $semester->load('academicYear');
    }

    public function changeSemester(int $semesterId): void
    {
        $target = Semester::query()->findOrFail($semesterId);
        $this->redirectRoute('report-cards.class-summary', [
            'rombel' => $this->rombel->id,
            'semester' => $target->id,
        ], navigate: true);
    }

    private function authorizeClassAccess(Rombel $rombel): void
    {
        $user = auth()->user();

        if (in_array($user?->role?->code, ['ADMIN', 'KEPALA_SEKOLAH'], true)) {
            return;
        }

        if ($user?->role?->code === 'WALI_KELAS') {
            $isAssigned = $user->classAssignments()
                ->where('rombel_id', $rombel->id)
                ->where('status', 'ACTIVE')
                ->exists();

            abort_unless($isAssigned, 403, 'Anda bukan wali kelas dari rombel ini.');

            return;
        }

        if ($user?->role?->code === 'OPERATOR') {
            $hasScope = $user->operatorRombelScopes()
                ->where('rombel_id', $rombel->id)
                ->where('is_active', true)
                ->exists();

            abort_unless($hasScope, 403, 'Rombel ini di luar cakupan tugas operator Anda.');

            return;
        }

        abort(403, 'Akses rekap kelas ditolak.');
    }

    public function bulkLock(): void
    {
        try {
            $count = app(\App\Services\ReportScoreWorkflowService::class)
                ->lockRombel(auth()->user(), $this->rombel, $this->semester);

            session()->flash('success', "Berhasil mengunci {$count} nilai rapor di kelas {$this->rombel->name}.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function bulkFinalize(): void
    {
        try {
            $count = app(\App\Services\ReportScoreWorkflowService::class)
                ->finalizeRombel(auth()->user(), $this->rombel, $this->semester);

            session()->flash('success', "Berhasil memfinalisasi {$count} nilai rapor di kelas {$this->rombel->name}.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        $enrollments = Enrollment::query()
            ->where('rombel_id', $this->rombel->id)
            ->where('academic_year_id', $this->semester->academic_year_id)
            ->where('status', 'ACTIVE')
            ->with('student')
            ->orderBy('student_id')
            ->get();

        $offerings = SubjectOffering::query()
            ->where('rombel_id', $this->rombel->id)
            ->where('semester_id', $this->semester->id)
            ->where('is_active', true)
            ->get();

        $totalOfferings = $offerings->count();
        $offeringIds = $offerings->pluck('id');

        $scores = ReportScore::query()
            ->whereIn('subject_offering_id', $offeringIds)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->groupBy('student_id');

        $studentRows = $enrollments->map(function ($enrollment) use ($scores, $totalOfferings) {
            $student = $enrollment->student;
            $studentScores = $scores->get($student->id, collect());

            $gradedCount = $studentScores->whereNotNull('final_score')->count();
            $average = $gradedCount > 0
                ? round($studentScores->whereNotNull('final_score')->avg('final_score'), 1)
                : null;

            $status = match (true) {
                $totalOfferings > 0 && $gradedCount === $totalOfferings => 'COMPLETE',
                $gradedCount > 0 => 'PARTIAL',
                default => 'EMPTY',
            };

            return [
                'student' => $student,
                'total_offerings' => $totalOfferings,
                'graded_count' => $gradedCount,
                'average_score' => $average,
                'status' => $status,
            ];
        });

        $completeStudents = $studentRows->where('status', 'COMPLETE')->count();
        $totalStudents = $studentRows->count();
        $classAverage = $studentRows->whereNotNull('average_score')->isNotEmpty()
            ? round($studentRows->whereNotNull('average_score')->avg('average_score'), 1)
            : null;

        $semesterOptions = Semester::query()
            ->with('academicYear')
            ->orderByDesc('academic_year_id')
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn ($s) => [
                $s->id => ($s->academicYear?->name ?? '-').' — '.$s->name,
            ])
            ->all();

        $allScores = $scores->flatten();
        $draftCount = $allScores->where('status', 'DRAFT')->count();
        $lockedCount = $allScores->where('status', 'LOCKED')->count();
        $finalizedCount = $allScores->where('status', 'FINALIZED')->count();

        $user = auth()->user();
        $canLock = $draftCount > 0 && $user->hasPermission('LOCK_REPORT_SCORE') && (
            $user->role?->code === 'ADMIN'
            || ($user->role?->code === 'WALI_KELAS' && $user->classAssignments()->where('rombel_id', $this->rombel->id)->where('status', 'ACTIVE')->exists())
            || ($user->role?->code === 'OPERATOR' && $user->operatorRombelScopes()->where('rombel_id', $this->rombel->id)->where('is_active', true)->exists())
        );

        $canFinalize = $lockedCount > 0 && $user->role?->code === 'ADMIN' && $user->hasPermission('FINALIZE_REPORT_SCORE');

        return [
            'studentRows' => $studentRows,
            'totalStudents' => $totalStudents,
            'completeStudents' => $completeStudents,
            'classAverage' => $classAverage,
            'totalOfferings' => $totalOfferings,
            'semesterOptions' => $semesterOptions,
            'draftCount' => $draftCount,
            'lockedCount' => $lockedCount,
            'finalizedCount' => $finalizedCount,
            'canLock' => $canLock,
            'canFinalize' => $canFinalize,
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">Rekap Rapor Kelas: {{ $rombel->name }}</h1>
                <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                    Tingkat {{ $rombel->grade }} · {{ $rombel->competency?->name }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Tahun Ajaran {{ $semester->academicYear?->name }} — {{ $semester->name }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($canLock)
                <button
                    type="button"
                    wire:click="bulkLock"
                    wire:confirm="Yakin ingin mengunci seluruh nilai DRAFT di kelas ini? Nilai yang dikunci siap untuk difinalisasi oleh Admin."
                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Kunci Nilai Kelas ({{ $draftCount }})
                </button>
            @endif

            @if ($canFinalize)
                <button
                    type="button"
                    wire:click="bulkFinalize"
                    wire:confirm="Yakin ingin memfinalisasi seluruh nilai terkunci di kelas ini? Nilai yang difinalisasi hanya dapat dikoreksi oleh Admin dengan alasan resmi."
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Finalisasi Nilai ({{ $lockedCount }})
                </button>
            @endif

            <a href="{{ route('report-cards.batch-pdf', ['rombel' => $rombel->id, 'semester' => $semester->id]) }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Ekspor Semua Rapor (ZIP PDF)
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <!-- Statistik Singkat -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Siswa Terdaftar</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 tabular-nums">{{ $totalStudents }}</p>
            <p class="mt-1 text-xs text-gray-400">Siswa aktif di rombel</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Kelengkapan Nilai</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600 tabular-nums">{{ $completeStudents }}/{{ $totalStudents }}</p>
            <p class="mt-1 text-xs text-gray-400">Siswa dengan nilai penuh</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Rata-rata Kelas</p>
            <p class="mt-2 text-3xl font-bold text-brand-600 tabular-nums">{{ $classAverage ?? '-' }}</p>
            <p class="mt-1 text-xs text-gray-400">Nilai akhir seluruh mapel</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mata Pelajaran</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 tabular-nums">{{ $totalOfferings }}</p>
            <p class="mt-1 text-xs text-gray-400">Ditawarkan semester ini</p>
        </div>
    </div>

    <!-- Filter Semester -->
    <div class="card p-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Pilih Semester:</label>
            <select wire:change="changeSemester($event.target.value)" class="w-auto text-sm">
                @foreach ($semesterOptions as $id => $label)
                    <option value="{{ $id }}" @selected($id == $semester->id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="text-xs text-gray-500">Klik "Buka Rapor" untuk melihat rincian capaian individu siswa.</p>
    </div>

    <!-- Tabel Siswa Rombel -->
    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-4 py-3 w-12 text-center">No</th>
                        <th class="px-4 py-3 w-28">NIS</th>
                        <th class="px-4 py-3">Nama Siswa</th>
                        <th class="px-3 py-3 w-36 text-center">Status Kelengkapan</th>
                        <th class="px-3 py-3 w-28 text-center">Mapel Dinilai</th>
                        <th class="px-3 py-3 w-28 text-center">Rata-rata</th>
                        <th class="px-4 py-3 w-32 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($studentRows as $idx => $row)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-4 py-3 text-center text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $row['student']->nis }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $row['student']->full_name }}</td>
                            <td class="px-3 py-3 text-center">
                                @if ($row['status'] === 'COMPLETE')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        Lengkap
                                    </span>
                                @elseif ($row['status'] === 'PARTIAL')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                        Sebagian
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">
                                        Belum Ada
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center tabular-nums text-gray-700">
                                {{ $row['graded_count'] }} / {{ $row['total_offerings'] }}
                            </td>
                            <td class="px-3 py-3 text-center font-bold tabular-nums text-gray-900">
                                {{ $row['average_score'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('report-cards.show', ['student' => $row['student']->id, 'semester' => $semester->id]) }}" class="inline-flex items-center justify-center rounded-md bg-white border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                                    Buka Rapor &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                Belum ada siswa yang terdaftar aktif di rombel ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
