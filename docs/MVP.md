# MVP — SmartRapor SMK

**Dokumen pendamping:** `docs/PRD.md`, `docs/ARCHITECTURE.md`
**Status:** Draft v1 — menunggu persetujuan pemilik
**Terakhir diperbarui:** 2026-10-16

## 1. Defenisi MVP

MVP = aplikasi buku induk yang sudah ada (Fase 0) **ditambah** alur e-rapor yang membuat satu semester bisa dikerjakan dari awal sampai rapor tercetak, tanpa langkah manual di luar aplikasi.

Kalimat uji MVP: *"Seorang wali kelas dapat mengisi ledger nilai Pengetahuan & Keterampilan untuk seluruh mapel di rombelnya, menguncinya; admin memfinalisasi; rapor tiap siswa tampil lengkap dan siap dicetak; siswa hanya melihat rapornya sendiri."*

## 2. Termasuk MVP (wajib)

| # | Fitur | Kriteria selesai |
|---|---|---|
| M1 | Ledger nilai per mapel (item PJ/KK, skor 0–100) | CRUD item saat DRAFT; minimal 1 PJ & 1 KK dapat divalidasi; data tersimpan di `report_score_items` |
| M2 | Rata-rata & nilai akhir terhitung | `final_score` header = gabungan rata-rata PJ & KK dengan **bobot per mapel** (diubah kapan pun saat DRAFT → recompute); tidak dapat diketik manual |
| M3 | Predikat + ketuntasan dari KKM | predikat A/B/C/D **langsung dihitung dari KKM** (`I=(100−KKM)/3`, batas riil/ceil); TUNTAS/BELUM TUNTAS = nilai akhir ≥ KKM; KKM default 70, wajib diisi sebelum finalisasi. **Tanpa** tabel grade_scales |
| M4 | Deskripsi capaian | ter-generate otomatis dari item tertinggi/terendah; editable saat DRAFT |
| M5 | Halaman rapor siswa × semester | identitas + kop/logo sekolah, tabel mapel, kehadiran, ekskul, **PKL, UKK, nilai ijazah**, catatan wali, blok tanda tangan |
| M6 | Cetak rapor on-demand | print CSS A4 presisi via browser; hasil print preview = tampilan layar; jalan offline di LAN |
| M7 | Rekap kelas per wali kelas | status kelengkapan nilai per siswa × mapel; lock massal rombel |
| M8 | Finalisasi & koreksi teraudit (regresi Fase 0) | lock→finalize tetap ADMIN-only untuk finalize; koreksi pasca-final wajib alasan + `audit_logs` |
| M9 | Otorisasi per peran | semua rute/page MVP baru dilindungi policy + permission; uji 403 lintas peran |
| M10 | Migrasi data lama | `report_scores` existing ter-backfill menjadi 1 item agregat per tipe tanpa kehilangan nilai |
| M11 | Upload identitas sekolah | Admin mengunggah logo + kop (nama, NPSN, alamat) yang tampil di rapor & PDF |
| M12 | Batch export PDF | dompdf/barryvdh: render rapor banyak siswa (per rombel × semester) jadi satu file ZIP |

## 3. Belum termasuk MVP

- Nilai BK/bel karakter, rapor per tema Kurikulum Merdeka.
- Integrasi Dapodik, notifikasi email/WA, multi-sekolah, portal ortu terpisah.
- Peningkatan tema UI besar-besaran (tetap Tailwind utility yang ada).
- Deploy produksi ke PC sekolah (masuk Fase 4, setelah MVP teruji di PC ini).

## 4. Sequencing (pemetaaan ke roadmap PRD)

1. **Fase 1** → M1, M2, M3, M4, M10 (backend ledger + bobot per mapel + perhitungan agregat/predikat/deskripsi + migrasi).
2. **Fase 2** → M5, M6, M9, M11, M12 (rapor lengkap + print on-demand + identitas/kop/logo + batch PDF + otorisasi halaman).
3. **Fase 3** → M7, M8 (rekap kelas, lock massal, kontrol mutu).

## 5. Definisi "Selesai" (Definition of Done)

- [x] `php artisan test` hijau, termasuk test Feature baru per M1–M12 (Pest, 135 tests, DB `buku_induk_smk_test`, `RefreshDatabase` aktif global).
- [x] `vendor/bin/pint --test` bersih tanpa peringatan style.
- [x] Walkthrough & route testing: login tiap peran, rute terisolasi, otorisasi policy/permissions.
- [x] Tidak ada rute baru tanpa `authorize`/policy check; seluruh route closure non-Livewire dipindahkan ke Controller (`StudentFileController`, `ReportCardController`).
- [x] Perubahan skema lewat migration yang dapat di-`migrate:fresh --seed` ulang.
- [x] Dokumen `docs/*` diperbarui sesuai implementasi final.

## 6. Keputusan (disetujui pemilik, 2026-10-16)

Tidak ada lagi yang memblokir mulai koding. Ringkasan (detail PRD §9):
- Bobot PJ:KK **per mata pelajaran**, ubah kapan pun (recompute agregat).
- Predikat **langsung diturunkan dari KKM per mapel** (`I=(100−KKM)/3`, batas riil/ceil); tidak ada tabel `grade_scales`/halaman kelola predikat. KKM default 70, wajib diisi sebelum finalisasi.
- Rapor memuat mapel + kehadiran + ekskul + **PKL + UKK + ijazah**.
- Fase 2 menyertakan **upload logo/kop** + **batch export PDF (dompdf)**; cetak on-demand tetap via print CSS browser.
