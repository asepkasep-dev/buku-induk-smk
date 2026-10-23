<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function logout()
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function with(): array
    {
        $user = auth()->user();
        $students = match ($user->role?->code) {
            'ADMIN', 'KEPALA_SEKOLAH' => Student::query(),
            'OPERATOR' => $user->accessibleStudentsAsOperator(),
            'WALI_KELAS' => $user->accessibleStudentsAsHomeroom(),
            'STUDENT' => Student::query()->whereKey($user->student_id),
            default => Student::query()->whereRaw('1 = 0'),
        };

        $school = School::query()->first();
        $academicYear = AcademicYear::query()
            ->where('status', 'ACTIVE')
            ->with(['semesters' => fn ($query) => $query->where('status', 'ACTIVE')->orderBy('number')])
            ->orderByDesc('start_date')
            ->first();

        $counts = [
            'all' => (clone $students)->count(),
            'active' => (clone $students)->where('status', 'AKTIF')->count(),
            'candidate' => (clone $students)->where('status', 'CALON')->count(),
            'graduated' => (clone $students)->where('status', 'LULUS')->count(),
        ];

        $other = max(0, $counts['all'] - $counts['active'] - $counts['candidate'] - $counts['graduated']);

        return [
            'school' => $school,
            'academicYear' => $academicYear,
            'studentCounts' => $counts,
            'studentDistribution' => [
                ['label' => 'Aktif', 'value' => $counts['active'], 'color' => '#16a34a'],
                ['label' => 'Calon siswa', 'value' => $counts['candidate'], 'color' => '#d97706'],
                ['label' => 'Lulus', 'value' => $counts['graduated'], 'color' => '#2563eb'],
                ['label' => 'Lainnya', 'value' => $other, 'color' => '#9ca3af'],
            ],
            'canViewStudents' => $user->hasPermission('VIEW_STUDENT'),
            'canCreateStudents' => $user->can('create', Student::class),
            'isAdmin' => $user->role?->code === 'ADMIN',
        ];
    }
};
?>

<div class="space-y-6">
    <section class="rounded-xl bg-gradient-to-r from-blue-800 to-indigo-700 p-6 text-white shadow-sm sm:p-8">
        <p class="text-sm font-medium text-blue-100">{{ $school?->name ?? 'Buku Induk Siswa SMK' }}</p>
        <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Selamat datang, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-blue-100">{{ auth()->user()->role?->name ?? 'Pengguna' }} · Sistem administrasi akademik sekolah</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @if ($canViewStudents)
                <a href="{{ route('students.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50">Buka data siswa</a>
            @endif
            @if ($canCreateStudents)
                <a href="{{ route('students.create') }}" class="rounded-lg border border-white/60 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Tambah siswa</a>
            @endif
            @if ($isAdmin)
                <a href="{{ route('admin.academic-setup') }}" class="rounded-lg border border-white/60 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Pengaturan akademik</a>
            @endif
        </div>
    </section>

    <section aria-labelledby="academic-period-heading" class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="academic-period-heading" class="text-sm font-medium text-gray-500">Periode akademik aktif</h2>
                @if ($academicYear)
                    <p class="mt-1 text-lg font-semibold">Tahun ajaran {{ $academicYear->name }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        @forelse ($academicYear->semesters as $semester)
                            <span>{{ $semester->name }}</span>@if (! $loop->last)<span> · </span>@endif
                        @empty
                            Semester aktif belum ditetapkan.
                        @endforelse
                    </p>
                @else
                    <p class="mt-1 text-lg font-semibold">Belum ada tahun ajaran aktif</p>
                    @if ($isAdmin)
                        <p class="mt-1 text-sm text-gray-600">Buat atau aktifkan tahun ajaran dari Pengaturan Akademik.</p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">Hubungi Admin sekolah untuk menyiapkan periode akademik.</p>
                    @endif
                @endif
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">{{ now()->format('d/m/Y') }}</span>
        </div>
    </section>

    @if ($canViewStudents)
        <section aria-labelledby="student-summary-heading" class="space-y-4">
            <h2 id="student-summary-heading" class="text-lg font-semibold">Ringkasan siswa dalam cakupan akses Anda</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'Semua siswa', 'value' => $studentCounts['all'], 'tone' => 'text-gray-900', 'bar' => 'bg-brand-600'],
                    ['label' => 'Siswa aktif', 'value' => $studentCounts['active'], 'tone' => 'text-green-700', 'bar' => 'bg-green-600'],
                    ['label' => 'Calon siswa', 'value' => $studentCounts['candidate'], 'tone' => 'text-amber-700', 'bar' => 'bg-amber-500'],
                    ['label' => 'Lulus', 'value' => $studentCounts['graduated'], 'tone' => 'text-blue-700', 'bar' => 'bg-blue-600'],
                ] as $i => $card)
                    <article class="stat-card fade-in-up" style="animation-delay: {{ $i * 70 }}ms">
                        <span class="mb-3 block h-1 w-10 rounded-full {{ $card['bar'] }}"></span>
                        <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold {{ $card['tone'] }} tabular-nums" data-count-to="{{ $card['value'] }}">{{ number_format($card['value']) }}</p>
                    </article>
                @endforeach
            </div>

            @php
                $distLabels = collect($studentDistribution)->pluck('label');
                $distValues = collect($studentDistribution)->pluck('value');
                $distColors = collect($studentDistribution)->pluck('color');
                $distTotal = $distValues->sum();
            @endphp

            <div class="card grid gap-6 p-6 md:grid-cols-[minmax(0,220px)_1fr] md:items-center">
                <div wire:ignore class="relative mx-auto h-44 w-44">
                    <canvas
                        data-chart="doughnut"
                        data-chart-labels="{{ $distLabels->toJson() }}"
                        data-chart-values="{{ $distValues->toJson() }}"
                        data-chart-colors="{{ $distColors->toJson() }}"
                        data-chart-empty="Belum ada data siswa untuk ditampilkan."
                    ></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-semibold tabular-nums text-gray-900" data-count-to="{{ $distTotal }}">{{ number_format($distTotal) }}</span>
                        <span class="text-xs text-gray-500">total siswa</span>
                    </div>
                </div>

                <ul class="grid gap-2 text-sm">
                    @foreach ($studentDistribution as $slice)
                        <li class="flex items-center justify-between gap-3 rounded-lg px-2 py-1 transition-colors hover:bg-slate-50">
                            <span class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $slice['color'] }}"></span>
                                <span class="text-gray-700">{{ $slice['label'] }}</span>
                            </span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ number_format($slice['value']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @else
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            Akun Anda belum memiliki izin untuk melihat data siswa. Hubungi Admin sekolah jika akses ini diperlukan.
        </section>
    @endif

    <div class="flex justify-end">
        <button type="button" wire:click="logout" class="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Keluar dengan aman</button>
    </div>
</div>
