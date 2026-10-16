# Panduan Agent — SmartRapor SMK (Buku Induk + E-Rapor)

Aplikasi Laravel untuk buku induk siswa dan e-rapor SMK, dijalankan di LAN sekolah. PHP 8.4, Laravel 13, Livewire 4 (single-file components), Alpine.js, Tailwind CSS 4, Vite 8, PostgreSQL. Bahasa komunikasi & komentar produk: Indonesia (istilah teknis Inggris boleh).

## WAJIB dibaca sebelum mengubah kode

1. `docs/PRD.md` — kebutuhan produk, roadmap fase, kriteria terima, dan **Keputusan Terbuka (§9)**.
2. `docs/MVP.md` — scope pasti MVP + Definition of Done.
3. `docs/ARCHITECTURE.md` — stack, skema data, model otorisasi, dan **utang teknis + aturan (§7)**.
4. `CLAUDE.md` + `.claude/skills/` — konvensi kode Laravel/Livewire/Pest dari Laravel Boost.

Kalau sebuah tugas menyentuh area yang keputusan terbukanya di PRD §9 belum dijawab, **tanya pemilik dulu**, jangan asumsi sendiri.

## Alur kerja per fitur (kesepakatan pemilik)

```
PRD  →  Frontend (stub komponen Livewire + Alpine, dummy via view)
     →  Backend (migration, model, Service, Policy)
     →  Uji coba di PC ini (php artisan test + walkthrough manual browser)
     →  perbaiki temuan → commit per fase
```

Kerja per fase secara otonom; laporkan saat milestone/fase selesai, jangan hasil parsial. Setiap fase ditutup hanya jika suite Pest hijau dan `vendor/bin/pint --test` bersih.

## Prasyarat & setup

Verifikasi tool:

```sh
php -v && composer -V && node -v
```

Setup sekali (jalankan dari root aplikasi):

```sh
composer setup          # install, .env, key:generate, migrate --seed, npm install + build
```

Jalankan dev: `composer dev` (serve + queue + vite). Test: `php artisan test` — **wajib** DB PostgreSQL terpisah `buku_induk_smk_test`, jangan pernah mengarahkan test ke database operasional. Buat akun admin pertama: `php artisan school:user-add admin@sekolah.local ADMIN`.

Deploy LAN (bukan untuk PC pengembangan): lihat `README.md` dan `scripts/` (package → install → backup → restore → test-school-app).

## Aturan kode ringkas (detail di ARCHITECTURE.md §7)

- Tulis-path berisiko → `app/Services/**` dengan `DB::transaction` + `lockForUpdate` + `AuditLog::create` (pola `ReportScoreWorkflowService`). Komponen Livewire tetap tipis: validasi + panggil service.
- Setiap rute/halaman baru harus lewat `authorize`/Policy + cek scope peran; tidak menambah closure route baru.
- Fitur e-rapor baru **tidak** menambah logika write ke komponen raksasa yang ada (`students/⚡records.blade.php`, `admin/academic-setup.blade.php`).
- Perubahan skema lewat migration yang aman untuk `migrate:fresh --seed`.
- Jangan aktifkan `RefreshDatabase` di `tests/Pest.php` tanpa memastikan test masih hijau (sedang jadi temuan).

## Catatan start saat ini

Working tree punya ±44 berkas belum di-commit (setup Docker/scripts, impor CSV, catatan akademik, rapor+test). Sebelum mulai Fase 1, commit per tema lebih dulu.
