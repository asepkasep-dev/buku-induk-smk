<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ReportScore;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\SubjectOffering;
use App\Services\ReportScoreWorkflowService;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedSemesterId = null;

    public function mount(): void
    {
        $this->authorizeAdminOrPrincipal();

        $activeSemester = Semester::query()->where('status', 'ACTIVE')->latest('id')->first();
        $this->selectedSemesterId = $activeSemester?->id
            ?? Semester::query()->latest('id')->value('id');
    }

    public function finalizeRombel(int $rombelId): void
    {
        abort_unless(auth()->user()?->role?->code === 'ADMIN', 403);

        $rombel = Rombel::query()->findOrFail($rombelId);
        $semester = Semester::query()->findOrFail($this->selectedSemesterId);

        try {
            $count = app(ReportScoreWorkflowService::class)
                ->finalizeRombel(auth()->user(), $rombel, $semester);

            session()->flash('success', "Berhasil memfinalisasi {$count} nilai rapor di kelas {$rombel->name}.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function lockRombel(int $rombelId): void
    {
        $rombel = Rombel::query()->findOrFail($rombelId);
        $semester = Semester::query()->findOrFail($this->selectedSemesterId);

        try {
            $count = app(ReportScoreWorkflowService::class)
                ->lockRombel(auth()->user(), $rombel, $semester);

            session()->flash('success', "Berhasil mengunci {$count} nilai rapor di kelas {$rombel->name}.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function authorizeAdminOrPrincipal(): void
    {
        abort_unless(in_array(auth()->user()?->role?->code, ['ADMIN', 'KEPALA_SEKOLAH'], true), 403);
    }

    public function with(): array
    {
        $this->authorizeAdminOrPrincipal();

        $semesters = Semester::query()
            ->with('academicYear')
            ->orderByDesc('academic_year_id')
            ->orderBy('number')
            ->get();

        $currentSemester = $semesters->firstWhere('id', $this->selectedSemesterId);

        if (! $currentSemester) {
            return [
                'semesters' => $semesters,
                'currentSemester' => null,
                'rombelRows' => collect(),
                'totalStudents' => 0,
                'totalRombels' => 0,
                'totalScores' => 0,
                'finalizedScores' => 0,
                'progressPercent' => 0,
                'isAdmin' => auth()->user()?->role?->code === 'ADMIN',
            ];
        }

        $rombels = Rombel::query()
            ->where('academic_year_id', $currentSemester->academic_year_id)
            ->where('is_active', true)
            ->with(['competency', 'classAssignments' => fn ($q) => $q->where('status', 'ACTIVE')->with('user')])
            ->orderBy('grade')
            ->orderBy('name')
            ->get();

        $rombelRows = $rombels->map(function (Rombel $rombel) use ($currentSemester) {
            $studentCount = Enrollment::query()
                ->where('rombel_id', $rombel->id)
                ->where('academic_year_id', $currentSemester->academic_year_id)
                ->where('status', 'ACTIVE')
                ->count();

            $offerings = SubjectOffering::query()
                ->where('rombel_id', $rombel->id)
                ->where('semester_id', $currentSemester->id)
                ->where('is_active', true)
                ->get();

            $missingKkmCount = $offerings->whereNull('kkm')->count();
            $offeringIds = $offerings->pluck('id');

            $scores = ReportScore::query()
                ->whereIn('subject_offering_id', $offeringIds)
                ->get();

            $draftCount = $scores->where('status', 'DRAFT')->count();
            $lockedCount = $scores->where('status', 'LOCKED')->count();
            $finalizedCount = $scores->where('status', 'FINALIZED')->count();
            $totalExpected = $studentCount * $offerings->count();

            $homeroom = $rombel->classAssignments->first()?->user;

            $status = match (true) {
                $totalExpected > 0 && $finalizedCount >= $totalExpected => 'FINALIZED',
                $lockedCount > 0 => 'LOCKED',
                $draftCount > 0 => 'DRAFT',
                default => 'EMPTY',
            };

            return [
                'rombel' => $rombel,
                'homeroom_name' => $homeroom?->name ?? 'Belum Ditugaskan',
                'student_count' => $studentCount,
                'offering_count' => $offerings->count(),
                'missing_kkm_count' => $missingKkmCount,
                'draft_count' => $draftCount,
                'locked_count' => $lockedCount,
                'finalized_count' => $finalizedCount,
                'total_expected' => $totalExpected,
                'status' => $status,
            ];
        });

        $totalStudents = $rombelRows->sum('student_count');
        $totalRombels = $rombelRows->count();
        $totalExpectedScores = $rombelRows->sum('total_expected');
        $finalizedScores = $rombelRows->sum('finalized_count');
        $progressPercent = $totalExpectedScores > 0
            ? (int) round(($finalizedScores / $totalExpectedScores) * 100)
            : 0;

        return [
            'semesters' => $semesters,
            'currentSemester' => $currentSemester,
            'rombelRows' => $rombelRows,
            'totalStudents' => $totalStudents,
            'totalRombels' => $totalRombels,
            'totalExpectedScores' => $totalExpectedScores,
            'finalizedScores' => $finalizedScores,
            'progressPercent' => $progressPercent,
            'isAdmin' => auth()->user()?->role?->code === 'ADMIN',
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Kontrol Mutu & Kelengkapan Rapor</h1>
            <p class="mt-1 text-sm text-gray-500">
                Pantau progres penilaian dan finalisasi rapor seluruh rombel di sekolah.
            </p>
        </div>

        <!-- Filter Semester -->
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Semester:</label>
            <select wire:model.live="selectedSemesterId" class="w-auto text-sm font-semibold">
                @foreach ($semesters as $s)
                    <option value="{{ $s->id }}">{{ $s->academicYear?->name ?? '-' }} — {{ $s->name }}</option>
                @endforeach
            </select>
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

    <!-- Kartu KPI Sekolah -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Siswa Aktif</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 tabular-nums">{{ $totalStudents }}</p>
            <p class="mt-1 text-xs text-gray-400">Di seluruh rombel</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Rombel</p>
            <p class="mt-2 text-3xl font-bold text-brand-600 tabular-nums">{{ $totalRombels }}</p>
            <p class="mt-1 text-xs text-gray-400">Tahun ajaran {{ $currentSemester?->academicYear?->name }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Difinalisasi</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600 tabular-nums">{{ $finalizedScores }}</p>
            <p class="mt-1 text-xs text-gray-400">Dari {{ $totalExpectedScores }} kuota nilai</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Progres Finalisasi</p>
            <p class="mt-2 text-3xl font-bold text-indigo-600 tabular-nums">{{ $progressPercent }}%</p>
            <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $progressPercent }}%"></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rombel -->
    <section class="card overflow-hidden">
        <div class="border-b bg-slate-50 px-5 py-4 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900 text-base">Kelengkapan Penilaian Rombel</h2>
            <span class="text-xs text-gray-500">{{ $rombelRows->count() }} Rombel Aktif</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b bg-slate-100/70 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-4 py-3 w-12 text-center">No</th>
                        <th class="px-4 py-3">Rombel / Kelas</th>
                        <th class="px-4 py-3">Wali Kelas</th>
                        <th class="px-3 py-3 w-20 text-center">Siswa</th>
                        <th class="px-3 py-3 w-24 text-center">Mapel</th>
                        <th class="px-3 py-3 w-32 text-center">Status KKM</th>
                        <th class="px-4 py-3 text-center">Rincian Status Nilai</th>
                        <th class="px-4 py-3 w-44 text-center">Aksi Kontrol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rombelRows as $idx => $row)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-4 py-3 text-center text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $row['rombel']->name }}</div>
                                <div class="text-xs text-gray-500">Tingkat {{ $row['rombel']->grade }} · {{ $row['rombel']->competency?->short_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $row['homeroom_name'] }}
                            </td>
                            <td class="px-3 py-3 text-center font-medium">{{ $row['student_count'] }}</td>
                            <td class="px-3 py-3 text-center text-gray-600">{{ $row['offering_count'] }}</td>
                            <td class="px-3 py-3 text-center">
                                @if ($row['missing_kkm_count'] === 0 && $row['offering_count'] > 0)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                        Lengkap
                                    </span>
                                @elseif ($row['offering_count'] === 0)
                                    <span class="text-xs text-gray-400">-</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">
                                        {{ $row['missing_kkm_count'] }} Kosong
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex items-center gap-1.5 text-xs">
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 font-mono" title="Draft">{{ $row['draft_count'] }} Draft</span>
                                    <span class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-800 font-mono" title="Terkunci">{{ $row['locked_count'] }} Kunci</span>
                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-800 font-mono font-semibold" title="Final">{{ $row['finalized_count'] }} Final</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('report-cards.class-summary', ['rombel' => $row['rombel']->id, 'semester' => $currentSemester->id]) }}"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 shadow-sm"
                                        title="Buka Rekap Kelas"
                                    >
                                        Rekap
                                    </a>

                                    @if ($row['draft_count'] > 0)
                                        <button
                                            type="button"
                                            wire:click="lockRombel({{ $row['rombel']->id }})"
                                            wire:confirm="Kunci seluruh nilai DRAFT di kelas {{ $row['rombel']->name }}?"
                                            class="inline-flex items-center rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700 shadow-sm"
                                            title="Kunci Seluruh Nilai"
                                        >
                                            Kunci
                                        </button>
                                    @endif

                                    @if ($isAdmin && $row['locked_count'] > 0)
                                        <button
                                            type="button"
                                            wire:click="finalizeRombel({{ $row['rombel']->id }})"
                                            wire:confirm="Finalisasi seluruh nilai terkunci di kelas {{ $row['rombel']->name }}?"
                                            class="inline-flex items-center rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700 shadow-sm"
                                            title="Finalisasi Nilai"
                                        >
                                            Finalkan
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                Tidak ada data rombel aktif pada tahun ajaran ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
