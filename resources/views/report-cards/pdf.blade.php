<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor {{ $student['full_name'] }} - Semester {{ $academic['semester_number'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #111;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-logo {
            width: 65px;
            text-align: left;
        }

        .header-logo img {
            max-width: 60px;
            max-height: 60px;
        }

        .header-text {
            text-align: center;
        }

        .header-text .title-gov {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-text .title-school {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0;
        }

        .header-text .title-address {
            font-size: 8pt;
            color: #333;
        }

        .report-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0;
            letter-spacing: 0.5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8pt;
        }

        .table-data th,
        .table-data td {
            border: 1px solid #333;
            padding: 4px 5px;
            vertical-align: middle;
        }

        .table-data th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .table-data .text-center { text-align: center; }
        .table-data .text-right { text-align: right; }
        .table-data .font-bold { font-weight: bold; }

        .category-row {
            background-color: #fafafa;
            font-weight: bold;
        }

        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            margin: 8px 0 4px 0;
        }

        .two-cols {
            width: 100%;
            border-collapse: collapse;
        }

        .two-cols td {
            vertical-align: top;
            padding: 0 4px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 8.5pt;
        }

        .signatures-table td {
            vertical-align: top;
            text-align: center;
            padding: 4px;
        }

        .signature-space {
            height: 55px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            @if ($school['logo_data_uri'])
                <td class="header-logo">
                    <img src="{{ $school['logo_data_uri'] }}" alt="Logo">
                </td>
            @endif
            <td class="header-text">
                <div class="title-school">{{ $school['name'] }}</div>
                <div class="title-address">
                    NPSN: {{ $school['npsn'] }} | NSS: {{ $school['nss'] }}<br>
                    {{ $school['address'] }}{{ $school['city'] ? ', '.$school['city'] : '' }}
                    @if ($school['phone'] && $school['phone'] !== '-') | Telp: {{ $school['phone'] }} @endif
                    @if ($school['website'] && $school['website'] !== '-') | Web: {{ $school['website'] }} @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="report-title">Laporan Hasil Belajar Peserta Didik</div>

    <table class="info-table">
        <tr>
            <td style="width: 18%;">Nama Peserta Didik</td>
            <td style="width: 2%;">:</td>
            <td style="width: 35%; font-weight: bold;">{{ $student['full_name'] }}</td>
            <td style="width: 18%;">Kelas / Rombel</td>
            <td style="width: 2%;">:</td>
            <td style="width: 25%;">{{ $academic['rombel_name'] }}</td>
        </tr>
        <tr>
            <td>NIS / NISN</td>
            <td>:</td>
            <td>{{ $student['nis'] }} / {{ $student['nisn'] }}</td>
            <td>Semester</td>
            <td>:</td>
            <td>{{ $academic['semester_name'] }}</td>
        </tr>
        <tr>
            <td>Program Keahlian</td>
            <td>:</td>
            <td>{{ $academic['competency_name'] }}</td>
            <td>Tahun Ajaran</td>
            <td>:</td>
            <td>{{ $academic['year_name'] }}</td>
        </tr>
    </table>

    <div class="section-heading">A. Nilai Akademik</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 32%;">Mata Pelajaran</th>
                <th style="width: 7%;">KKM</th>
                <th style="width: 8%;">Pengetahuan</th>
                <th style="width: 8%;">Keterampilan</th>
                <th style="width: 8%;">Nilai Akhir</th>
                <th style="width: 7%;">Predikat</th>
                <th style="width: 26%;">Deskripsi Capaian</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse ($subjects_by_category as $categoryLabel => $groupSubjects)
                <tr class="category-row">
                    <td colspan="8"><strong>{{ $categoryLabel }}</strong></td>
                </tr>
                @foreach ($groupSubjects as $s)
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td>{{ $s['subject_name'] }}</td>
                        <td class="text-center">{{ $s['kkm'] }}</td>
                        <td class="text-center">{{ $s['pengetahuan'] !== null ? number_format((float)$s['pengetahuan'], 1) : '-' }}</td>
                        <td class="text-center">{{ $s['keterampilan'] !== null ? number_format((float)$s['keterampilan'], 1) : '-' }}</td>
                        <td class="text-center font-bold">{{ $s['final_score'] ?? '-' }}</td>
                        <td class="text-center font-bold">{{ $s['letter'] }}</td>
                        <td style="font-size: 7.5pt;">{{ $s['description'] }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 10px;">Belum ada mata pelajaran pada semester ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="two-cols">
        <tr>
            <td style="width: 50%;">
                <div class="section-heading">B. Kehadiran</div>
                <table class="table-data">
                    <tr>
                        <td style="width: 70%;">Sakit</td>
                        <td class="text-center font-bold" style="width: 30%;">{{ $attendance['sick'] }} hari</td>
                    </tr>
                    <tr>
                        <td>Izin</td>
                        <td class="text-center font-bold">{{ $attendance['excused'] }} hari</td>
                    </tr>
                    <tr>
                        <td>Tanpa Keterangan</td>
                        <td class="text-center font-bold">{{ $attendance['absent'] }} hari</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <div class="section-heading">C. Ekstrakurikuler</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Kegiatan</th>
                            <th style="width: 20%;">Predikat</th>
                            <th style="width: 30%;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($extracurriculars as $ekskul)
                            <tr>
                                <td>{{ $ekskul['name'] }}</td>
                                <td class="text-center font-bold">{{ $ekskul['predicate'] }}</td>
                                <td style="font-size: 7.5pt;">{{ $ekskul['description'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">-</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @if (!empty($pkl))
        <div class="section-heading">D. Praktik Kerja Lapangan (PKL)</div>
        <table class="table-data">
            <thead>
                <tr>
                    <th style="width: 25%;">Mitra DU/DI</th>
                    <th style="width: 20%;">Lokasi</th>
                    <th style="width: 20%;">Waktu Pelaksanaan</th>
                    <th style="width: 15%;">Nilai Kompetensi</th>
                    <th style="width: 20%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pkl as $p)
                    <tr>
                        <td>{{ $p['partner'] }}</td>
                        <td>{{ $p['location'] }}</td>
                        <td class="text-center">{{ $p['duration'] }}</td>
                        <td class="text-center font-bold">{{ $p['competency_score'] ?? '-' }} ({{ $p['competency_predicate'] }})</td>
                        <td style="font-size: 7.5pt;">{{ $p['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($ukk))
        <div class="section-heading">E. Uji Kompetensi Keahlian (UKK)</div>
        <table class="table-data">
            <thead>
                <tr>
                    <th style="width: 60%;">Kompetensi Keahlian</th>
                    <th style="width: 20%;">Skor Akhir</th>
                    <th style="width: 20%;">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ukk as $u)
                    <tr>
                        <td>{{ $u['competency'] }}</td>
                        <td class="text-center font-bold">{{ $u['score'] ?? '-' }}</td>
                        <td class="text-center font-bold">{{ $u['predicate'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="signatures-table">
        <tr>
            <td style="width: 33%;">
                Mengetahui,<br>Orang Tua / Wali
                <div class="signature-space"></div>
                <strong>..................................................</strong>
            </td>
            <td style="width: 33%;">
                <br>
                <!-- space -->
            </td>
            <td style="width: 34%;">
                {{ $signatures['place_date'] }}<br>Wali Kelas
                <div class="signature-space"></div>
                <strong>{{ $signatures['homeroom_name'] }}</strong>
                @if ($signatures['homeroom_nip'] && $signatures['homeroom_nip'] !== '-')
                    <br><span style="font-size: 8pt;">NIP. {{ $signatures['homeroom_nip'] }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; padding-top: 15px;">
                Mengetahui,<br>Kepala Sekolah
                <div class="signature-space"></div>
                <strong>{{ $signatures['principal_name'] }}</strong>
                @if ($signatures['principal_nip'] && $signatures['principal_nip'] !== '-')
                    <br><span style="font-size: 8pt;">NIP. {{ $signatures['principal_nip'] }}</span>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
