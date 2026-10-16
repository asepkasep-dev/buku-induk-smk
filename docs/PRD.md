# PRD — SmartRapor SMK (Buku Induk + E-Rapor)

**Nama proyek:** Buku Induk SMK / SmartRapor
**Stack:** TALL — Laravel 13, Livewire 4, Alpine.js, Tailwind CSS 4, PostgreSQL 16, Vite 8, PHP 8.4
**Penanggung jawab:** pemilik produk merangkap pengembang (kerja dibantu AI agent)
**Status dokumen:** Draft v1 — menunggu persetujuan pemilik
**Terakhir diperbarui:** 2026-10-16

---

## 1. Latar Belakang & Visi

Sekolah SMK membutuhkan satu aplikasi internal untuk:

1. **Buku induk digital** — data siswa terpusat, riwayat akademik lengkap, dokumen privat.
2. **E-rapor** — nilai rapor per semester dengan ledger Pengetahuan & Keterampilan, predikat otomatis, deskripsi capaian, hingga cetak rapor — dijalankan di LAN sekolah (bukan layanan publik internet).

Fase 0 (fondasi buku induk + nilai rapor tunggal) **sudah berjalan**. PRD ini menaikkan aplikasi menjadi e-rapor lengkap.

## 2. Kondisi Saat Ini (Fase 0 — selesai)

Sudah tersedia (bukti di kode):

- Login berbasis peran: ADMIN, OPERATOR, WALI_KELAS, KEPALA_SEKOLAH, STUDENT (23 permission, `PermissionSeeder`).
- Penyiapan akademik: profil sekolah, tahun ajaran + semester, konsentrasi keahlian, rombel, kurikulum, mapel, mapping kurikulum→mapel→kompetensi→tingkat/semester, offering mapel ke rombel, enrollment siswa.
- Registrasi siswa: CRUD + impor CSV (pratinjau, maks 500 baris, deteksi duplikat nis/nisn/nik), wali/orang tua, dokumen privat.
- Akun & scope: halaman Pengelolaan Akun, tugas wali kelas per rombel/tahun, scope operator per rombel.
- Catatan akademik siswa: kehadiran, ekstrakurikuler, PKL, UKK, nilai ijazah, kelulusan (DRAFT→VERIFIED→FINALIZED).
- Nilai rapor **nilai akhir tunggal** per siswa × offering (`report_scores.final_score` + `letter_grade` manual), workflow DRAFT→LOCKED→FINALIZED + koreksi beralasan, audit log (`ReportScoreWorkflowService`, `ReportScorePolicy`).
- Deploy LAN: Docker Compose + proxy TLS (Caddy), skrip PowerShell install/backup/restore/test (`scripts/`).

**Gap terhadap e-rapor lengkap:**

| Gap | Dampak |
|---|---|
| Tidak ada ledger nilai per kompetensi/pertemuan | Rapor SMK sejati butuh nilai Pengetahuan & Keterampilan per mapel |
| Predikat/letter grade diketik manual, tidak ada aturan konversi | Tidak konsisten antar guru |
| Tidak ada deskripsi capaian otomatis | Guru menulis manual panjang |
| Tidak ada tampilan rapor & cetak/PDF | Output utama e-rapor belum ada |
| Tidak ada rekap kelas per wali kelas | Pengawasan kelengkapan nilai sulit |

## 3. Pengguna & Cerita Inti

| Peran | Kebutuhan utama |
|---|---|
| Admin / Waka Kurikulum | Menyiapkan struktur akademik, finalisasi massal, koreksi terkontrol, cetak laporan kelengkapan |
| Operator / TU | Input/massal data siswa & nilai sesuai scope rombel, upload dokumen |
| Wali Kelas | Input nilai Pengetahuan/Keterampilan rombelnya, lihat rekap kelas, kunci nilai, cetak rapor siswanya |
| Kepala Sekolah | Melihat rapor & memverifikasi kelulusan; tidak mengubah data |
| Siswa / Orang Tua | Melihat rapor miliknya sendiri (read-only) via HTTPS LAN |

## 4. Ruang Lingkup Produk

### 4.1 Modul E-Rapor (baru)

**Nilai per mata pelajaran (ledger).**
- Setiap mata pelajaran yang di-offer-kan ke rombel memiliki **ledger nilai**: daftar baris penilaian bertipe `PENGETAHUAN` atau `KETERAMPILAN` (label bebas, mis. "UH 1 Topik X", "Praktikum Y"), skor 0–100.
- Nilai akhir mapel = gabungan rata-rata Pengetahuan & Keterampilan dengan **bobot per mata pelajaran** (mis. 60:40), diatur wali kelas/operator dan **dapat diubah kapan pun** selama DRAFT; perubahan memicu recompute agregat.
- Ledger hanya dapat diubah selama status `DRAFT`.

**Predikat otomatis (langsung dari KKM — tanpa tabel ambang).**
- Sekolah/guru menginput **KKM (Kriteria Ketuntasan Minimal)** per mata pelajaran (kolom `kkm` pada offering). Bila belum diisi, default **70**, dan **wajib diisi sebelum finalisasi**.
- Ketuntasan: `TUNTAS` jika nilai akhir ≥ KKM, selainnya `BELUM TUNTAS`.
- **Predikat diturunkan dari KKM** dengan interval `I = (100 − KKM) / 3` dan titik potong riil (batas bawah tiap pita = integer pertama ≥ titik potong, `ceil`):
  - `D` : nilai < KKM
  - `C` : KKM ≤ nilai < KKM + I
  - `B` : KKM + I ≤ nilai < KKM + 2I
  - `A` : KKM + 2I ≤ nilai ≤ 100
- Contoh: KKM 70 → C 70–79 / B 80–89 / A 90–100. KKM 75 (I=8,33) → C 75–83 / B 84–91 / A 92–100.
- Tidak ada lagi tabel `grade_scales` / halaman "Kelola Predikat"; predikat murni fungsi dari KKM + nilai akhir.

**Deskripsi capaian otomatis.**
- Generator teks per mapel: menyebut 2 nilai tertinggi dan 1 terendah dari item Pengetahuan (pola "dengan kemampuan …, siswa perlu peningkatan pada …") + predikat. Editable selama DRAFT.

**Rapor siswa & cetak.**
- Halaman rapor per siswa × semester: identitas + kop/line sekolah (nama, NPSN, logo hasil upload), tabel mapel (Pengetahuan, Keterampilan, Akhir, Huruf/Predikat, Deskripsi), kehadiran, ekstrakurikuler, **PKL, UKK, dan nilai ijazah**, catatan wali, blok tanda tangan (wali kelas + kepala sekolah + tanggal).
- Cetak/PDF: **dua jalur** — (a) print CSS A4 presisi via browser untuk cetak on-demand tanpa beban server; (b) **batch export PDF server-side** (dompdf/barryvdh) untuk menghasilkan rapor banyak siswa sekaligus (per rombel/semester) sebagai satu ZIP.
- Bobot penilaian Pengetahuan vs Keterampilan **dapat diatur per mata pelajaran** dan **dapat diubah kapan pun** (bukan hanya default 50:50); perubahan bobot memicu hitung ulang agregat mapel terkait.
- Akses mengikuti policy: siswa hanya rapor dirinya, wali hanya rombelnya.

**Rekap & finalisasi kelas (wali kelas).**
- Daftar semua siswa rombel per semester dengan status kelengkapan nilai per mapel (jumlah item PJ/KK), indikator merah/kuning/hijau.
- Aksi massal: lock semua DRAFT rombel setelah validasi kelengkapan.

**Upgrade alur nilai yang ada.**
- `report_scores` tetap header (unik siswa × offering) dengan status DRAFT→LOCKED→FINALIZED + koreksi ADMIN beralasan (sudah ada) — kolom `final_score` dan `letter_grade` menjadi **turunan** dari ledger, bukan input manual.

### 4.2 Non-fungsional

- **Keamanan:** tetap LAN-only; sesi terenkripsi; throttle login; otorisasi per permission + scope rombel; dokumen privat tidak bisa diambil tanpa izin; semua aksi nilai tercatat `audit_logs`.
- **Performa:** 500–1.500 siswa; halaman rekap kelas ≤ 2 detik pada PC server 8 GB.
- **Offline-tolerant:** tanpa CDN; seluruh asset ter-bundle Vite di server.
- **Kompatibilitas browser:** Chrome/Edge/Firefox modern di PC pengguna.
- **Backup:** snapshot DB + dokumen via skrip yang ada; ZIP wajib dienkripsi sebelum keluar PC server.

### 4.3 Di luar lingkup (bukan MVP)

- Portal orang tua terpisah, notifikasi email/WA, kurikulum/diagram nilai real-time, integrasi Dapodik.
- Multi-sekolah (single tenant tetap).
- Nilai BK/bel karakter & rapor per tema Kurikulum Merdeka penuh (menyusul jika diminta).

## 5. Alur Kerja Pengembangan per Fase

Sesuai gaya kerja pemilik proyek:

```
PRD (dokumen ini, disetujui) 
  → Frontend: stub halaman/komponen Livewire + Alpine interaktivitas, dummy data via view 
  → Backend: migrasi, model/service/policy, integrasi data nyata 
  → Uji coba di PC ini: php artisan test (DB buku_induk_smk_test) + manual walkthrough browser (php artisan serve / docker compose) 
  → perbaiki temuan → commit per fase
```

Setiap fase harus meninggalkan suite Pest yang hijau sebelum lanjut.

## 6. Roadmap Fase

| Fase | Isi | Ekspor utama |
|---|---|---|
| **0 — Fondasi** | buku induk, authz, catatan akademik, nilai tunggal + workflow, deploy LAN | ✅ selesai |
| **1 — Ledger & Predikat** | tabel `report_score_items`; kolom bobot & KKM di `subject_offerings`; UI input ledger (Alpine); hitung rata-rata, predikat (dari KKM) & deskripsi otomatis; migrasi data `report_scores` lama ke 1 item agregat per tipe | backend nilai e-rapor |
| **2 — Rapor & Cetak** | halaman rapor siswa×semester (mapel + kehadiran + ekskul + PKL + UKK + ijazah), rekap kelas wali, print CSS A4 + kop/logo/tanda tangan (upload logo), **batch export PDF server-side** (ZIP per rombel/semester), pembatasan akses | rapor tercetak + PDF massal |
| **3 — Finalisasi Massal & Kontrol Mutu** | lock massal per rombel/semester dg validasi kelengkapan, dashboard kelengkapan admin, uji koreksi teraudit | alur semester penuh |
| **4 — Hardening & Uji Lapangan** | perbaikan temuan review (RefreshDatabase test, service split `records`, controller routes), uji install/restore di PC kandidat, backup terenkripsi | siap dipakai sekolah |
| 5 (opsional) | integrasi Dapodik, BK/bel karakter, rapor per tema Kurikulum Merdeka | menyusul |

## 7. Kriteria Terima (acceptance) tingkat produk

1. Wali kelas dapat mengisi ledger PJ/KK ≥1 item per mapel offering untuk siswanya, melihat rata-rata & predikat terhitung otomatis, lalu mengunci.
2. Admin memfinalisasi per semester; koreksi setelah final hanya ADMIN + alasan + tercatat di audit (regresi dari Fase 0).
3. Rapor semester siswa tampil lengkap dan identik saat dicetak (print preview) — termasuk kehadiran, ekskul, tanda tangan.
4. Siswa login hanya melihat rapor miliknya; wali kelas lain, operator lain, dan peran lain ditolak (403).
5. Semua halaman baru lolos `php artisan test` dan walkthrough manual di PC ini.

## 8. Risiko & Mitigasi

- **Skema nilai berubah saat data real sudah masuk** → migrasi Fase 1 menyediakan backfill aman + uji di DB test lebih dulu; jangan sentuh data nyata sebelum Fase 4 tervalidasi.
- **Komponen Livewire raksasa** (`students/⚡records.blade.php` ±525 baris) makin sulit dirawat → aturan baru di `docs/ARCHITECTURE.md`: logika e-rapor masuk Service, komponen tipis.
- **Perangkat sekolah lama** → targetkan fitur browser standar (tanpa WebGL/WebSocket), `wire:navigate` opsional.
- **44 berkas working tree belum di-commit** → commit per tema sebelum mulai Fase 1 (lihat catatan AGENTS.md).

## 9. Keputusan Final Pemilik (2026-10-16)

1. **Bobot PJ:KK tidak fixed** — dapat diatur per mata pelajaran, kapan pun.
2. **Predikat langsung dari KKM** (keputusan final 2026-10-16): interval `I=(100−KKM)/3`, batas pakai titik potong riil (ceil). **Tidak ada** tabel `grade_scales`/halaman kelola predikat. KKM default 70 bila kosong, wajib diisi sebelum finalisasi.
3. **Rapor memuat** nilai rapor per mapel + kehadiran + ekstrakurikuler + PKL + UKK + nilai ijazah, termasuk cetak/PDF rapor.
4. **Kop & tanda tangan** dimasukkan Fase 2, **termasuk upload logo sekolah**.
5. **Batch export PDF (dulunya Fase 5) dinaikkan ke MVP.**
6. **Cetak ganda:** browser print untuk on-demand per siswa + PDF server-side untuk batch per rombel/semester.
