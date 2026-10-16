# Architecture — SmartRapor SMK (Buku Induk + E-Rapor)

**Stack:** TALL — Laravel 13, Livewire 4 (single-file components), Alpine.js (bawaan Livewire), Tailwind CSS 4, Vite 8, PostgreSQL 16, PHP 8.4
**Dokumen terkait:** `docs/PRD.md`, `docs/MVP.md`
**Status:** v1 — menggambarkan kondisi Fase 0 + rencana perubahan Fase 1–3
**Terakhir diperbarui:** 2026-10-16

## 1. Konteks & Batasan

- Single-tenant (satu sekolah), dijalankan di LAN via Docker Compose + proxy TLS (Caddy), PC Windows sebagai server. **Bukan** layanan internet publik.
- Asset harus ter-bundle lokal (tanpa CDN) agar jalan saat internet server mati.
- Data siswa & dokumen privat = data sensitif; proteksi lewat permission + scope rombel, bukan hanya "login".

## 2. Lapisan Aplikasi

```
Browser (Alpine via Livewire) 
  → routes/web.php (Route::livewire per halaman) 
  → Komponen Livewire single-file (resources/views/components/pages/**⚡*.blade.php) 
  → Policy (app/Policies) + User::hasPermission (role→permission) 
  → Service (app/Services) untuk write-path bertransaksi + audit 
  → Eloquent models (app/Models) → PostgreSQL
Audit: audit_logs (JSON before/after) di setiap mutasi penting
```

**Konvensi yang berlaku di repo:**

- Komponen Livewire = satu file `.blade.php` dengan blok `<?php new class extends Component ... ?>` di atas markup. Nama file berprefiks `⚡` untuk komponen yang di-register by path.
- Halaman admin khusus (academic-setup, users) mengunci diri dengan `abort_unless(role ADMIN)` privat, bukan policy.
- Write-path berisiko memakai `DB::transaction` + `lockForUpdate` + `AuditLog::create` (pola `ReportScoreWorkflowService`).
- Test: Pest 5, Feature-only, DB pgsql terpisah `buku_induk_smk_test`; `RefreshDatabase` sedang **dikomentari** di `tests/Pest.php` (temuan review, lihat §7).

## 3. Skema Data Inti (kondisi saat ini)

### Struktur akademik
`schools` → identitas & kepala sekolah.
`academic_years` (status INACTIVE|ACTIVE|COMPLETED) → `semesters` (number,status).
`competencies` (konsentrasi) ; `rombels` (tahun × kompetensi × grade × nama) ; `curricula` ; `subjects` ; `curriculum_subjects` (mapel per kurikulum+kompetensi+tingkat+semester) ; `subject_offerings` (curriculum_subject × rombel × semester) — **offering = unit tempat nilai rapor menempel**.
`enrollments` (siswa × rombel, status ACTIVE).

### Pengguna & otorisasi
`roles` (ADMIN, OPERATOR, WALI_KELAS, KEPALA_SEKOLAH, STUDENT) ⇄ `permissions` via `permission_role` (23 permission). `users` punya `role_id` + `student_id` unik (akun siswa). `class_assignments` (wali kelas → rombel per tahun, satu ACTIVE per rombel+tahun). `operator_rombel_scopes` (scope data operator).

### Siswa & catatan
`students` (nis/nisn/nik unik, status CALON|AKTIF|LULUS|PINDAH|MENGUNDURKAN_DIRI|TIDAK_LULUS) ; `guardians` + `student_guardians` ; `attendance_summaries` (DRAFT|LOCKED|FINALIZED) ; `extracurricular_records` ; `pkl_records` ; `ukk_records` ; `diploma_scores` ; `graduations` (DRAFT|VERIFIED|FINALIZED) ; `student_documents` (file privat, disk local) ; `audit_logs`.

### Nilai rapor (objek upgrade MVP)
`report_scores`: unik (student_id × subject_offering_id), `final_score` (int, nullable), `letter_grade` (string manual), `description`, status DRAFT|LOCKED|FINALIZED, kolom lock/finalize (by/at). **Belum ada ledger per item.**

## 4. Model Otorisasi

- Gate dasar: `$user->hasPermission(CODE)` — role→permission via pivot.
- Gate scope: untuk OPERATOR → `accessibleStudentsAsOperator()`; WALI_KELAS → `accessibleStudentsAsHomeroom()`; STUDENT → `student_id === $student->id`; ADMIN/KEPALA_SEKOLAH → seluruh siswa (view).
- Alur status: input hanya saat DRAFT; lock (DRAFT→LOCKED); finalize (LOCKED→FINALIZED, ADMIN only); koreksi pasca-final (ADMIN + permission + alasan + audit).
- **Temuan:** pola cek role + scope diulang identik di `StudentPolicy` & `ReportScorePolicy` → akan dipadatkan ke trait `ScopesStudentsToRole` pada Fase 1 (PRD risiko #2).

## 5. Arsitektur E-Rapor (rencana Fase 1–3)

### Skema baru

```
report_score_items
  id, report_score_id FK→report_scores (cascade), 
  type ENUM('PENGETAHUAN','KETERAMPILAN'),
  label VARCHAR (nama ujian/topik), 
  score SMALLINT (0–100), 
  sort_order SMALLINT, 
  created_by FK users, 
  timestamps
  INDEX (report_score_id, type)

subject_offerings (kolom baru untuk bobot & KKM per mapel)
  pengetahuan_weight SMALLINT default 50,
  keterampilan_weight SMALLINT default 50,   (pengetahuan+keterampilan = 100)
  kkm SMALLINT default 70                     (KKM per mapel; diinput sekolah/guru)
```

Tidak ada tabel `grade_scales` — predikat **murni diturunkan dari KKM**. Bobot & KKM disimpan **per offering** (satu mapel di satu rombel×semester) sehingga guru mapel dapat mengatur/mengubah kapan pun; perubahan memicu recompute semua `report_scores` turunan offering itu.

Aturan predikat (`GradePredicateResolver`), interval `I = (100 − kkm) / 3`, batas riil (ceil):
`D: < kkm`, `C: [kkm, kkm+I)`, `B: [kkm+I, kkm+2I)`, `A: [kkm+2I, 100]`. Ketuntasan = `final_score >= kkm`. Wajib KKM terisi sebelum finalisasi.

`report_scores` berubah peran: header + **kolom turunan** (`pengetahuan_avg`, `keterampilan_avg`, `final_score`, `letter_grade`, `description` ter-generate). Input manual ke kolom turunan dihapus dari UI; satu-satunya write-path nilai = lewat ledger.

### Layanan baru (pola mengikuti `ReportScoreWorkflowService`)

- `ReportScoreLedgerService` — CRUD item + recompute agregat + transisi DRAFT-guard + audit, semua `DB::transaction`+`lockForUpdate`.
- `GradePredicateResolver` — skor + KKM offering → huruf/predikat (interval `(100−KKM)/3`, batas riil/ceil).
- `ReportDescriptionGenerator` — teks capaian dari item (2 tertinggi, 1 terendah).
- `ReportCardAssembler` — menyusun data rapor siswa×semester (mapel + kehadiran + ekskul + **PKL + UKK + ijazah** + identitas + kop/logo + penanda tangan) untuk view, print CSS, dan PDF.
- `ReportCardPdfService` — render blade rapor ke PDF via `barryvdh/laravel-dompdf`; generator batch per rombel×semester → simpan banyak PDF ke ZIP (Storage) untuk diunduh.
- Migration `report_scores` lama: backfill tiap baris → 1 item PENGETAHUAN + 1 item KETERAMPILAN (label "Nilai existing", skor = `final_score` lama; skip bila null).
- Migration `schools`: tambah `logo_path` (+ kolom kop bila perlu); upload lewat halaman Admin academic-setup, validate mime image.

### Halaman / rute baru

```
/students/{student}/report-scores/{offering}/ledger   ledger + bobot (Livewire, M1–M4)
/students/{student}/report-cards/{semester}           tampilan rapor (M5)
/students/{student}/report-cards/{semester}/print     layout print A4 on-demand (M6)
/class/{rombel}/report-cards/{semester}               rekap + batch PDF rombel (M7, M12)
```

- Komponen Livewire tipis: validasi + panggil service; tidak ada query berat inline di `with()` seperti di `⚡records.blade.php` saat ini.
- Rapor & rekap = halaman read-only; aksi tulis (ledger, bobot, lock massal, generate batch) lewat controller/service ber-authorization (hindari closure route baru — temuan review).
- Cetak ganda: `layouts/print.blade.php` (Tailwind `@media print`, A4, header tabel berulang, kolom tanda tangan) untuk on-demand; **batch PDF server-side** via `barryvdh/laravel-dompdf` (perlu `composer require`) untuk ekspor rombel→ZIP.

### Interaksi frontend (Alpine)

- Form ledger: tambah/hapus baris dinamis (`Alpine` array repeat via `wire:model` koleksioner Livewire), kalkulasi rata-rata live di client untuk UX, **nilai final tetap dihitung server** (satu sumber kebenaran).
- Rekap kelas: filter semester + status kelengkapan (badge hijau/kuning/merah) pakai `wire:navigate`/`wire:key`, tanpa endpoint baru.
- Pratinjau rapor: tombol "Cetak" = `window.print()`; skeleton loading Livewire standar.

## 6. Build, Uji & Deploy

- Dev lokal: `composer setup`, `npm run build`/`dev`, `php artisan serve`, `php artisan test` (DB `buku_induk_smk_test`), `vendor/bin/pint`.
- Uji per PC ini (permintaan pemilik): jalankan `php artisan test` + walkthrough manual tiap peran lewat browser terhadap `php artisan serve`; hasil dicatat di checklist fase.
- Paket LAN: `scripts/package-school-server.ps1` → ZIP + `.sha256` → `install-school-server.ps1` di PC server; backup `backup-school-database.ps1` (ZIP **harus dienkripsi** sebelum keluar server), restore `restore-school-database.ps1`; test terisolasi `test-school-app.ps1`.

## 7. Utang Teknis & Aturan Baru (wajib dipatuhi agent)

1. `RefreshDatabase` di `tests/Pest.php` dikomentari → antar-test saling mencemari DB `buku_induk_smk_test`. **Aturan: aktifkan kembali saat mulai Fase 1**; bila tidak bisa, dokumentasikan cleanup eksplisit.
2. Komponen raksasa (`students/⚡records.blade.php` ±525 baris, `admin/academic-setup.blade.php`) menampung logika bisnis inline. **Aturan: fitur e-rapor baru tidak menambah logika write ke file-file itu; pakai Service.**
3. Duplikasi blok role/scope di policy → trait `ScopesStudentsToRole` (Fase 1).
4. Route closure untuk download dokumen & template CSV → pindahkan ke controller (Fase 4).
5. `canAccessOwnStudent()` muncul di model `Student` dan `User` → hapus duplikat bila dead code (cek dulu).
6. `SESSION_SECURE_COOKIE=false` padahal akses HTTPS → set `true` saat deploy sekolah (Fase 4).
7. **44 berkas working tree belum di-commit** → commit per tema (deploy Docker+scripts, CSV import, catatan akademik, rapor+test) **sebelum** mulai Fase 1.

## 8. Keputusan Arsitektur (ADR ringkas)

- **Livewire single-file, bukan Inertia/Livewire class terpisah** — sudah jadi konvensi repo, cocok untuk tim 1 orang + AI agent.
- **Cetak dua jalur: print-browser untuk on-demand + dompdf untuk batch** — on-demand tanpa beban server; PDF server-side hanya saat ekspor massal per rombel (keputusan pemilik 2026-10-16).
- **Ledger sebagai anak report_scores (bukan replace)** — mempertahankan workflow/audit Fase 0 dan unik (siswa × offering).
- **Bobot & KKM per offering (`subject_offerings`)** — predikat diturunkan langsung dari KKM, tanpa tabel ambang; guru mapel mengubah bobot/KKM tanpa deploy kode.
