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

        return [
            'school' => $school,
            'academicYear' => $academicYear,
            'studentCounts' => [
                'all' => (clone $students)->count(),
                'active' => (clone $students)->where('status', 'AKTIF')->count(),
                'candidate' => (clone $students)->where('status', 'CALON')->count(),
                'graduated' => (clone $students)->where('status', 'LULUS')->count(),
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
        <section aria-labelledby="student-summary-heading">
            <h2 id="student-summary-heading" class="mb-3 text-lg font-semibold">Ringkasan siswa dalam cakupan akses Anda</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'Semua siswa', 'value' => $studentCounts['all'], 'tone' => 'text-gray-900'],
                    ['label' => 'Siswa aktif', 'value' => $studentCounts['active'], 'tone' => 'text-green-700'],
                    ['label' => 'Calon siswa', 'value' => $studentCounts['candidate'], 'tone' => 'text-amber-700'],
                    ['label' => 'Lulus', 'value' => $studentCounts['graduated'], 'tone' => 'text-blue-700'],
                ] as $card)
                    <article class="rounded-xl border bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold {{ $card['tone'] }}">{{ number_format($card['value']) }}</p>
                    </article>
                @endforeach
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
