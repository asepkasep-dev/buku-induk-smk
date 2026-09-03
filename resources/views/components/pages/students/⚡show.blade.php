<?php

use App\Models\Student;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student->load([
            'enrollments.academicYear',
            'enrollments.rombel.competency',
            'studentGuardians.guardian',
            'reportScores.subjectOffering.curriculumSubject.subject',
            'reportScores.subjectOffering.semester.academicYear',
        ]);
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Detail Siswa</h1>
            <p class="mt-1 text-sm text-gray-500">
                Informasi identitas dasar siswa.
            </p>
        </div>

        <a
            href="{{ route('students.index') }}"
            class="text-sm text-blue-600 hover:underline"
        >
            Kembali
        </a>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="text-lg font-semibold">Identitas Siswa</h2>

        <dl class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <dt class="text-sm text-gray-500">NIS</dt>
                <dd class="mt-1 font-medium">{{ $student->nis }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">NISN</dt>
                <dd class="mt-1 font-medium">{{ $student->nisn ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Nama Lengkap</dt>
                <dd class="mt-1 font-medium">{{ $student->full_name }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Nama Panggilan</dt>
                <dd class="mt-1 font-medium">{{ $student->nickname ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Jenis Kelamin</dt>
                <dd class="mt-1 font-medium">
                    {{ $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : '-') }}
                </dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Tempat, Tanggal Lahir</dt>
                <dd class="mt-1 font-medium">
                    {{ $student->birth_place ?? '-' }},
                    {{ $student->birth_date?->format('d-m-Y') ?? '-' }}
                </dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Agama</dt>
                <dd class="mt-1 font-medium">{{ $student->religion ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Tahun Masuk</dt>
                <dd class="mt-1 font-medium">{{ $student->entry_year }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Status Siswa</dt>
                <dd class="mt-1 font-medium">{{ $student->status }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Sekolah Asal</dt>
                <dd class="mt-1 font-medium">{{ $student->previous_school ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="text-lg font-semibold">Alamat & Kontak</h2>

        <dl class="mt-4 grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <dt class="text-sm text-gray-500">Alamat</dt>
                <dd class="mt-1 font-medium">{{ $student->address ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Kelurahan / Desa</dt>
                <dd class="mt-1 font-medium">{{ $student->village ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Kecamatan</dt>
                <dd class="mt-1 font-medium">{{ $student->district ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Kota / Kabupaten</dt>
                <dd class="mt-1 font-medium">{{ $student->city ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Provinsi</dt>
                <dd class="mt-1 font-medium">{{ $student->province ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Telepon</dt>
                <dd class="mt-1 font-medium">{{ $student->phone ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Email</dt>
                <dd class="mt-1 font-medium">{{ $student->email ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="text-lg font-semibold">Riwayat Rombel</h2>

        <div class="mt-4 overflow-hidden rounded-lg border">
            <table class="min-w-full divide-y">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Tahun Ajaran</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Rombel</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Kompetensi</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse ($student->enrollments->sortByDesc('academic_year_id') as $enrollment)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $enrollment->academicYear?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $enrollment->rombel?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $enrollment->rombel?->competency?->short_name
                                    ?? $enrollment->rombel?->competency?->name
                                    ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $enrollment->status }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                Belum ada riwayat rombel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="text-lg font-semibold">Data Orang Tua / Wali</h2>

        <div class="mt-4 overflow-hidden rounded-lg border">
            <table class="min-w-full divide-y">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Nama</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Hubungan</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Telepon</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Pekerjaan</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Utama</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse ($student->studentGuardians as $studentGuardian)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $studentGuardian->guardian?->full_name ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $studentGuardian->relationship }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $studentGuardian->guardian?->phone ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $studentGuardian->guardian?->occupation ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $studentGuardian->is_primary ? 'Ya' : 'Tidak' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                Belum ada data orang tua / wali.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="text-lg font-semibold">Nilai Rapor</h2>

        @php
            $scoresBySemester = $student->reportScores
                ->groupBy(fn ($score) => $score->subjectOffering?->semester_id);
        @endphp

        <div class="mt-4 space-y-6">
            @forelse ($scoresBySemester as $scores)
                @php
                    $semester = $scores->first()?->subjectOffering?->semester;
                @endphp

                <div>
                    <h3 class="font-medium">
                        {{ $semester?->academicYear?->name ?? '-' }}
                        —
                        {{ $semester?->name ?? '-' }}
                    </h3>

                    <div class="mt-3 overflow-hidden rounded-lg border">
                        <table class="min-w-full divide-y">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Mata Pelajaran</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Nilai</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Huruf</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Aksi</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y">
                                @foreach ($scores as $score)
                                    <tr>
                                        <td class="px-4 py-3">
                                            {{ $score->subjectOffering?->curriculumSubject?->subject?->name ?? '-' }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $score->final_score ?? 'Belum Diinput' }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $score->letter_grade ?? '-' }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $score->status }}
                                        </td>

                                        <td class="px-4 py-3">
                                            @can('view', $score)
                                                <a
                                                    href="{{ route('report-scores.edit', $score) }}"
                                                    class="text-blue-600 hover:underline"
                                                >
                                                    Lihat Nilai
                                                </a>
                                            @else
                                                <span>-</span>
                                            @endcan
                                        </td>
                                    </tr>

                                    @if ($score->description)
                                        <tr>
                                            <td colspan="5" class="bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                                <strong>Deskripsi:</strong> {{ $score->description }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">
                    Belum ada nilai rapor.
                </p>
            @endforelse
        </div>
    </div>
</div>