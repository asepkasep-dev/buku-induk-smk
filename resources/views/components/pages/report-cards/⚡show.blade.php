<?php

use App\Models\Semester;
use App\Models\Student;
use App\Services\ReportCardAssembler;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    public Semester $semester;

    public array $cardData = [];

    public array $semesterOptions = [];

    public function mount(Student $student, Semester $semester): void
    {
        $this->authorize('view', $student);

        $this->student = $student;
        $this->semester = $semester->load('academicYear');

        $this->cardData = app(ReportCardAssembler::class)->assemble($this->student, $this->semester);

        $this->semesterOptions = Semester::query()
            ->with('academicYear')
            ->orderByDesc('academic_year_id')
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn ($s) => [
                $s->id => ($s->academicYear?->name ?? '-').' — '.$s->name,
            ])
            ->all();
    }

    public function changeSemester(int $semesterId): void
    {
        $target = Semester::query()->findOrFail($semesterId);
        $this->redirectRoute('report-cards.show', [
            'student' => $this->student->id,
            'semester' => $target->id,
        ], navigate: true);
    }

    public function with(): array
    {
        return [
            'canManageScores' => auth()->user()->hasPermission('INPUT_REPORT_SCORE'),
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">Rapor Hasil Belajar Siswa</h1>
                @if ($cardData['summary']['is_complete'])
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        Nilai Lengkap
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        {{ $cardData['summary']['graded_subjects'] }}/{{ $cardData['summary']['total_subjects'] }} Mapel Dinilai
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-gray-500">
                {{ $student->full_name }} (NIS: {{ $student->nis }}) · Kelas {{ $cardData['academic']['rombel_name'] }} · {{ $cardData['academic']['year_name'] }} - {{ $cardData['academic']['semester_name'] }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('students.show', $student) }}" class="btn-secondary">
                &larr; Data Siswa
            </a>
            <a href="{{ route('report-cards.print', ['student' => $student->id, 'semester' => $semester->id]) }}" target="_blank" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Rapor A4
            </a>
            <a href="{{ route('report-cards.pdf', ['student' => $student->id, 'semester' => $semester->id]) }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Unduh PDF Rapor
            </a>
        </div>
    </div>

    <!-- Pilih Semester -->
    <div class="card p-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-gray-700">Pilih Semester:</span>
            <select wire:change="changeSemester($event.target.value)" class="w-auto text-sm font-semibold">
                @foreach ($semesterOptions as $id => $label)
                    <option value="{{ $id }}" @selected($id == $semester->id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-6 text-sm">
            <div>
                <span class="text-gray-500">Rata-rata Nilai:</span>
                <span class="ml-1 font-bold text-gray-900">{{ $cardData['summary']['average_final_score'] ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500">Total Mapel:</span>
                <span class="ml-1 font-semibold text-gray-900">{{ $cardData['summary']['total_subjects'] }}</span>
            </div>
        </div>
    </div>

    <!-- Tabel Nilai Akademik -->
    <section class="card overflow-hidden">
        <div class="border-b bg-slate-50 px-5 py-3.5 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900 text-base">A. Nilai Capaian Kompetensi</h2>
            <span class="text-xs text-gray-500">Nilai akhir dihitung berdasarkan bobot Pengetahuan & Keterampilan</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b bg-slate-100/70 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-4 py-3 w-12 text-center">No</th>
                        <th class="px-4 py-3">Mata Pelajaran</th>
                        <th class="px-3 py-3 w-16 text-center">KKM</th>
                        <th class="px-3 py-3 w-24 text-center">Pengetahuan</th>
                        <th class="px-3 py-3 w-24 text-center">Keterampilan</th>
                        <th class="px-3 py-3 w-24 text-center">Nilai Akhir</th>
                        <th class="px-3 py-3 w-20 text-center">Predikat</th>
                        <th class="px-4 py-3">Deskripsi Capaian</th>
                        @if ($canManageScores)
                            <th class="px-3 py-3 w-20 text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php $index = 1; @endphp
                    @forelse ($cardData['subjects_by_category'] as $categoryLabel => $items)
                        <tr class="bg-slate-50 font-semibold text-xs text-gray-700">
                            <td colspan="{{ $canManageScores ? 9 : 8 }}" class="px-4 py-2">{{ $categoryLabel }}</td>
                        </tr>
                        @foreach ($items as $s)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3 text-center text-gray-500">{{ $index++ }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $s['subject_name'] }}</td>
                                <td class="px-3 py-3 text-center text-gray-600">{{ $s['kkm'] }}</td>
                                <td class="px-3 py-3 text-center tabular-nums">
                                    {{ $s['pengetahuan'] !== null ? number_format((float)$s['pengetahuan'], 1) : '-' }}
                                </td>
                                <td class="px-3 py-3 text-center tabular-nums">
                                    {{ $s['keterampilan'] !== null ? number_format((float)$s['keterampilan'], 1) : '-' }}
                                </td>
                                <td class="px-3 py-3 text-center font-bold tabular-nums {{ $s['tuntas'] ? 'text-gray-900' : 'text-rose-600' }}">
                                    {{ $s['final_score'] ?? '-' }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center justify-center font-semibold rounded-md px-2 py-0.5 text-xs {{ $s['letter'] === 'A' ? 'bg-emerald-50 text-emerald-700' : ($s['letter'] === 'B' ? 'bg-blue-50 text-blue-700' : ($s['letter'] === 'C' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700')) }}">
                                        {{ $s['letter'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 leading-relaxed">{{ $s['description'] }}</td>
                                @if ($canManageScores)
                                    <td class="px-3 py-3 text-center">
                                        <a href="{{ route('report-scores.ledger', $s['offering_id']) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800 hover:underline">
                                            Ledger
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="{{ $canManageScores ? 9 : 8 }}" class="px-4 py-8 text-center text-gray-500">
                                Belum ada mata pelajaran yang ditawarkan untuk kelas ini pada semester terkait.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Ringkasan Tambahan (Kehadiran & Ekskul) -->
    <div class="grid gap-6 md:grid-cols-2">
        <!-- Kehadiran -->
        <section class="card p-5">
            <h2 class="font-semibold text-base mb-3 text-gray-900">B. Catatan Kehadiran</h2>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg bg-slate-50 p-3 text-center border">
                    <p class="text-xs text-gray-500">Sakit</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $cardData['attendance']['sick'] }}</p>
                    <p class="text-[10px] text-gray-400">hari</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3 text-center border">
                    <p class="text-xs text-gray-500">Izin</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $cardData['attendance']['excused'] }}</p>
                    <p class="text-[10px] text-gray-400">hari</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3 text-center border">
                    <p class="text-xs text-gray-500">Tanpa Keterangan</p>
                    <p class="mt-1 text-xl font-bold {{ $cardData['attendance']['absent'] > 3 ? 'text-rose-600' : 'text-gray-900' }}">
                        {{ $cardData['attendance']['absent'] }}
                    </p>
                    <p class="text-[10px] text-gray-400">hari</p>
                </div>
            </div>
        </section>

        <!-- Ekstrakurikuler -->
        <section class="card p-5">
            <h2 class="font-semibold text-base mb-3 text-gray-900">C. Kegiatan Ekstrakurikuler</h2>
            <div class="divide-y divide-gray-100">
                @forelse ($cardData['extracurriculars'] as $e)
                    <div class="py-2.5 flex items-start justify-between gap-3 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ $e['name'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $e['description'] }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700">
                            Predikat {{ $e['predicate'] }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-4 text-center">Belum ada catatan ekstrakurikuler semester ini.</p>
                @endforelse
            </div>
        </section>
    </div>

    <!-- PKL & UKK jika ada -->
    @if (!empty($cardData['pkl']) || !empty($cardData['ukk']))
        <div class="grid gap-6 md:grid-cols-2">
            @if (!empty($cardData['pkl']))
                <section class="card p-5">
                    <h2 class="font-semibold text-base mb-3 text-gray-900">D. Praktik Kerja Lapangan (PKL)</h2>
                    <div class="space-y-3">
                        @foreach ($cardData['pkl'] as $p)
                            <div class="rounded-lg border p-3 text-xs bg-slate-50/50">
                                <div class="flex justify-between items-center font-medium text-gray-900">
                                    <span>{{ $p['partner'] }}</span>
                                    <span class="font-bold text-brand-600">{{ $p['competency_score'] }} ({{ $p['competency_predicate'] }})</span>
                                </div>
                                <p class="text-gray-500 mt-1">Lokasi: {{ $p['location'] }} · Waktu: {{ $p['duration'] }}</p>
                                <p class="text-gray-600 mt-1">{{ $p['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (!empty($cardData['ukk']))
                <section class="card p-5">
                    <h2 class="font-semibold text-base mb-3 text-gray-900">E. Uji Kompetensi Keahlian (UKK)</h2>
                    <div class="space-y-3">
                        @foreach ($cardData['ukk'] as $u)
                            <div class="rounded-lg border p-3 text-xs bg-slate-50/50 flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $u['competency'] }}</p>
                                    <p class="text-gray-500">Predikat: {{ $u['predicate'] }}</p>
                                </div>
                                <span class="text-lg font-bold text-gray-900">{{ $u['score'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
</div>
