<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rapor - {{ $student['full_name'] }} - Semester {{ $academic['semester_number'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                color: black !important;
                font-size: 11px !important;
                padding: 0 !important;
            }
            .print-page {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .page-break {
                page-break-after: always;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
        }

        .print-page {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 20mm 15mm;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            font-size: 11px;
            line-height: 1.4;
            color: #111827;
        }
    </style>
</head>
<body class="bg-slate-100 text-gray-900 antialiased min-h-screen py-8">
    <!-- Action Toolbar (hidden on print) -->
    <div class="no-print fixed top-4 right-4 z-50 flex items-center gap-3 bg-white/95 backdrop-blur shadow-lg border border-gray-200 rounded-xl px-4 py-2.5">
        <a href="{{ route('report-cards.show', ['student' => $student['id'], 'semester' => $academic['semester_id']]) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            &larr; Kembali
        </a>
        <div class="h-4 w-px bg-gray-300"></div>
        <a href="{{ route('report-cards.pdf', ['student' => $student['id'], 'semester' => $academic['semester_id']]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
            Unduh PDF
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak (Print)
        </button>
    </div>

    <!-- Printable Report Sheet -->
    <main class="print-page">
        <!-- Kop Surat -->
        <header class="flex items-center gap-4 border-b-2 border-black pb-3 mb-4">
            @if ($school['logo_path'])
                <img src="{{ asset('storage/'.$school['logo_path']) }}" alt="Logo" class="h-16 w-16 object-contain shrink-0">
            @endif
            <div class="flex-1 text-center">
                <h1 class="text-base font-bold uppercase tracking-wider text-black">{{ $school['name'] }}</h1>
                <p class="text-xs text-gray-700">
                    NPSN: {{ $school['npsn'] }} | NSS: {{ $school['nss'] }}<br>
                    {{ $school['address'] }}{{ $school['city'] ? ', '.$school['city'] : '' }}
                    @if ($school['phone'] && $school['phone'] !== '-') | Telp: {{ $school['phone'] }} @endif
                    @if ($school['website'] && $school['website'] !== '-') | Web: {{ $school['website'] }} @endif
                </p>
            </div>
        </header>

        <h2 class="text-center font-bold text-sm uppercase tracking-wide my-3">
            Laporan Hasil Belajar Peserta Didik
        </h2>

        <!-- Data Siswa & Periode -->
        <div class="grid grid-cols-2 gap-4 text-xs mb-4 pb-2 border-b">
            <div class="space-y-1">
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">Nama Siswa</span>
                    <span class="col-span-2 font-bold">: {{ $student['full_name'] }}</span>
                </div>
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">NIS / NISN</span>
                    <span class="col-span-2">: {{ $student['nis'] }} / {{ $student['nisn'] }}</span>
                </div>
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">Program Keahlian</span>
                    <span class="col-span-2">: {{ $academic['competency_name'] }}</span>
                </div>
            </div>
            <div class="space-y-1">
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">Kelas / Rombel</span>
                    <span class="col-span-2 font-semibold">: {{ $academic['rombel_name'] }}</span>
                </div>
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">Semester</span>
                    <span class="col-span-2">: {{ $academic['semester_name'] }}</span>
                </div>
                <div class="grid grid-cols-3">
                    <span class="text-gray-600">Tahun Ajaran</span>
                    <span class="col-span-2">: {{ $academic['year_name'] }}</span>
                </div>
            </div>
        </div>

        <!-- Tabel Nilai Akademik -->
        <section class="mb-4">
            <h3 class="font-bold text-xs mb-1.5 uppercase text-gray-800">A. Nilai Akademik</h3>
            <table class="w-full border-collapse border border-gray-400 text-xs">
                <thead>
                    <tr class="bg-gray-100 text-gray-800">
                        <th class="border border-gray-400 px-2 py-1.5 w-8 text-center">No</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-left">Mata Pelajaran</th>
                        <th class="border border-gray-400 px-2 py-1.5 w-12 text-center">KKM</th>
                        <th class="border border-gray-400 px-2 py-1.5 w-16 text-center">Pengetahuan</th>
                        <th class="border border-gray-400 px-2 py-1.5 w-16 text-center">Keterampilan</th>
                        <th class="border border-gray-400 px-2 py-1.5 w-16 text-center">Nilai Akhir</th>
                        <th class="border border-gray-400 px-2 py-1.5 w-12 text-center">Predikat</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-left">Deskripsi Capaian</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @forelse ($subjects_by_category as $categoryLabel => $groupSubjects)
                        <tr class="bg-gray-50/70 font-semibold text-gray-800">
                            <td colspan="8" class="border border-gray-400 px-2 py-1">{{ $categoryLabel }}</td>
                        </tr>
                        @foreach ($groupSubjects as $s)
                            <tr>
                                <td class="border border-gray-400 px-2 py-1 text-center">{{ $no++ }}</td>
                                <td class="border border-gray-400 px-2 py-1">{{ $s['subject_name'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center">{{ $s['kkm'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center">{{ $s['pengetahuan'] !== null ? number_format((float)$s['pengetahuan'], 1) : '-' }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center">{{ $s['keterampilan'] !== null ? number_format((float)$s['keterampilan'], 1) : '-' }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $s['final_score'] ?? '-' }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $s['letter'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-[10px] leading-tight text-gray-700">{{ $s['description'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="border border-gray-400 px-3 py-4 text-center text-gray-500">Belum ada nilai mata pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <!-- Kehadiran & Ekskul -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <section>
                <h3 class="font-bold text-xs mb-1.5 uppercase text-gray-800">B. Kehadiran</h3>
                <table class="w-full border-collapse border border-gray-400 text-xs">
                    <tr>
                        <td class="border border-gray-400 px-2 py-1 w-2/3">Sakit</td>
                        <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $attendance['sick'] }} hari</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">Izin</td>
                        <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $attendance['excused'] }} hari</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">Tanpa Keterangan</td>
                        <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $attendance['absent'] }} hari</td>
                    </tr>
                </table>
            </section>

            <section>
                <h3 class="font-bold text-xs mb-1.5 uppercase text-gray-800">C. Ekstrakurikuler</h3>
                <table class="w-full border-collapse border border-gray-400 text-xs">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left">Kegiatan</th>
                            <th class="border border-gray-400 px-2 py-1 text-center w-16">Predikat</th>
                            <th class="border border-gray-400 px-2 py-1 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($extracurriculars as $ekskul)
                            <tr>
                                <td class="border border-gray-400 px-2 py-1">{{ $ekskul['name'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $ekskul['predicate'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-[10px]">{{ $ekskul['description'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="border border-gray-400 px-2 py-2 text-center text-gray-400">-</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        @if (!empty($pkl))
            <section class="mb-4">
                <h3 class="font-bold text-xs mb-1.5 uppercase text-gray-800">D. Praktik Kerja Lapangan (PKL)</h3>
                <table class="w-full border-collapse border border-gray-400 text-xs">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left">Mitra DU/DI</th>
                            <th class="border border-gray-400 px-2 py-1 text-left">Lokasi</th>
                            <th class="border border-gray-400 px-2 py-1 text-center">Waktu</th>
                            <th class="border border-gray-400 px-2 py-1 text-center">Nilai</th>
                            <th class="border border-gray-400 px-2 py-1 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pkl as $p)
                            <tr>
                                <td class="border border-gray-400 px-2 py-1">{{ $p['partner'] }}</td>
                                <td class="border border-gray-400 px-2 py-1">{{ $p['location'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center">{{ $p['duration'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $p['competency_score'] ?? '-' }} ({{ $p['competency_predicate'] }})</td>
                                <td class="border border-gray-400 px-2 py-1 text-[10px]">{{ $p['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if (!empty($ukk))
            <section class="mb-4">
                <h3 class="font-bold text-xs mb-1.5 uppercase text-gray-800">E. Uji Kompetensi Keahlian (UKK)</h3>
                <table class="w-full border-collapse border border-gray-400 text-xs">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left">Kompetensi Keahlian</th>
                            <th class="border border-gray-400 px-2 py-1 text-center w-24">Skor Akhir</th>
                            <th class="border border-gray-400 px-2 py-1 text-center w-24">Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ukk as $u)
                            <tr>
                                <td class="border border-gray-400 px-2 py-1">{{ $u['competency'] }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $u['score'] ?? '-' }}</td>
                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ $u['predicate'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <!-- Blok Tanda Tangan -->
        <section class="mt-8 text-xs">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p>Mengetahui,<br>Orang Tua / Wali Siswa</p>
                    <div class="h-16"></div>
                    <p class="font-semibold underline">..................................................</p>
                </div>
                <div></div>
                <div>
                    <p>{{ $signatures['place_date'] }}<br>Wali Kelas</p>
                    <div class="h-16"></div>
                    <p class="font-semibold underline">{{ $signatures['homeroom_name'] }}</p>
                    @if ($signatures['homeroom_nip'] && $signatures['homeroom_nip'] !== '-')
                        <p class="text-[10px] text-gray-600">NIP. {{ $signatures['homeroom_nip'] }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 text-center">
                <p>Mengetahui,<br>Kepala Sekolah</p>
                <div class="h-16"></div>
                <p class="font-semibold underline">{{ $signatures['principal_name'] }}</p>
                @if ($signatures['principal_nip'] && $signatures['principal_nip'] !== '-')
                    <p class="text-[10px] text-gray-600">NIP. {{ $signatures['principal_nip'] }}</p>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
