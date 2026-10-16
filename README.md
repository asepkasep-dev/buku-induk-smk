# Buku Induk SMK

Aplikasi Laravel untuk data siswa dan nilai rapor, disiapkan untuk dipakai bersama di jaringan lokal sekolah. Admin dapat mengatur profil sekolah, tahun ajaran, rombel, kurikulum, penempatan siswa, akun staf, dan cakupan akses.

## Menjalankan bersama di jaringan sekolah

Pola pemasangan yang disediakan memakai satu PC Windows sebagai server dan PostgreSQL di dalam Docker. Guru dan staf membuka aplikasi lewat browser dari PC lain di jaringan yang sama. PC server harus selalu menyala. Jangan jadikan aplikasi sebagai layanan internet publik.

### Kebutuhan PC server

- Windows 11 64-bit dengan virtualisasi aktif dan izin memasang Docker Desktop. Windows 10 hanya dipakai jika edisi tersebut masih menerima pembaruan keamanan (misalnya melalui program ESU atau siklus dukungan LTSC); Windows 10 umum telah mencapai akhir dukungan.
- Docker Desktop dengan Docker Compose v2 dalam mode Linux containers.
- Koneksi internet saat pemasangan pertama untuk mengambil image dan dependency aplikasi.
- Alamat IP tetap atau DHCP reservation pada jaringan sekolah.
- RAM 8 GB atau lebih disarankan jika Docker Desktop berbagi PC dengan pekerjaan lain.

PC pengguna hanya memerlukan browser modern dan pemasangan sertifikat CA sekolah satu kali.

Sebelum memilih PC server, jalankan `.\scripts\check-school-server.ps1` pada kandidat PC. Hasilnya menandai prasyarat Windows, virtualisasi, RAM, Docker, jaringan, profil firewall, dan akses administrator. Minta persetujuan petugas TI sekolah untuk Docker, sertifikat CA, dan aturan firewall sebelum memasang.

### Pemasangan pertama

1. Buat paket untuk dipindahkan dengan `.\scripts\package-school-server.ps1`, lalu salin ZIP dan file `.sha256` pendampingnya ke PC server. Verifikasi bahwa nilai pada file `.sha256` sama dengan hasil `Get-FileHash <nama-zip> -Algorithm SHA256`, ekstrak ZIP, lalu buka PowerShell pada folder hasil ekstrak. Paket memuat kode dari working tree saat ini dan mengecualikan konfigurasi rahasia serta data lokal.
2. Jalankan `.\scripts\install-school-server.ps1`. Skrip membuat `.env.school` berisi kunci aplikasi dan kata sandi database acak, membangun aplikasi, menyiapkan PostgreSQL, menjalankan migration dan seeder, lalu meminta pembuatan akun admin pertama. File rahasia `.env.school` tidak masuk Git.
3. Skrip membuat aturan firewall untuk port HTTPS dan pengalihan HTTP jika PowerShell dijalankan sebagai Administrator. Jika skrip dijalankan tanpa hak Administrator, pemasangan tetap berjalan tetapi PC lain belum bisa terhubung; jalankan ulang skrip sebagai Administrator atau minta petugas TI membuat aturan TCP 8443 dan 8080 untuk `LocalSubnet` pada profil **Private** atau **Domain**. Aturan tidak dibuka pada profil **Public**.
4. Salin `certs\school-root-ca.crt` ke setiap PC pengguna. Di PowerShell Administrator pada tiap PC, jalankan:

   ```powershell
   Import-Certificate -FilePath .\school-root-ca.crt -CertStoreLocation Cert:\LocalMachine\Root
   ```

5. Buka alamat HTTPS yang dicetak skrip, misalnya `https://192.168.1.20:8443`, lalu masuk dengan akun admin yang baru dibuat.

### Penyiapan setelah masuk

1. Di **Pengaturan Akademik**, lengkapi profil sekolah, buat tahun ajaran (semester ganjil/genap dibuat otomatis), lalu aktifkan tahun dan semester yang berlaku.
2. Tambahkan konsentrasi keahlian, rombel, kurikulum, mata pelajaran, struktur mapel per tingkat/semester, dan penawaran mapel ke rombel.
3. Tambahkan siswa satu per satu atau gunakan **Impor CSV** dari halaman Siswa. Impor menampilkan pratinjau dan menolak seluruh file jika ada baris atau nomor identitas ganda.
4. Tempatkan siswa ke rombel dan pastikan penawarannya cocok dengan tingkat, konsentrasi, tahun ajaran, serta semester sebelum memasukkan nilai.
5. Di **Pengelolaan Akun**, buat akun operator dan wali kelas. Batasi akses operator ke rombel yang menjadi tugasnya dan tugaskan wali kelas ke satu rombel per tahun ajaran.

Jika staf lupa kata sandi, admin server dapat mengatur ulang tanpa menampilkan kata sandi pada riwayat terminal:

```powershell
docker compose --env-file .env.school run --rm web php artisan school:user-password-reset operator@sekolah.local
```

Sertifikat root hanya berisi kunci publik. Salin melalui media atau folder instalasi yang dikelola sekolah. Jangan menyalin direktori data Docker ke PC pengguna.

Jika alamat IP server berubah, perbarui `APP_HOST` dan `APP_URL` pada `.env.school`, lalu jalankan kembali skrip pemasangan. Atur DHCP reservation agar tautan dan sertifikat tetap berlaku.

### Operasi harian

Docker Desktop menjalankan aplikasi dan database dengan kebijakan restart otomatis. PC server harus tetap menyala dan tersambung ke jaringan sekolah. Untuk melihat status dan log:

```powershell
docker compose --env-file .env.school ps
docker compose --env-file .env.school logs --tail 100 web proxy db
```

Buat backup berkala di media yang berbeda dari PC server:

```powershell
.\scripts\backup-school-database.ps1
```

Skrip menghentikan aplikasi sebentar agar database dan dokumen cocok dalam satu snapshot. File ZIP mencakup database serta dokumen siswa privat dan **tidak dienkripsi**. Cocokkan SHA-256 dari file `.sha256`, lalu enkripsikan ZIP sebelum menyalinnya ke media berbeda atau simpan pada media terenkripsi dengan akses terbatas. Dekripsikan kembali ke file ZIP aslinya sebelum restore. Lakukan uji pemulihan sebelum mulai memakai data nyata.

Untuk memulihkan backup lengkap **ke server aktif** (database dan dokumen saat ini akan diganti), jalankan:

```powershell
$backup = Get-ChildItem .\backups\BukuIndukSMK-*.zip | Sort-Object LastWriteTime -Descending | Select-Object -First 1
.\scripts\restore-school-database.ps1 -BackupPath $backup.FullName
```

Skrip memverifikasi hash bila file `.sha256` tersedia, meminta konfirmasi ketik `RESTORE`, lalu memulihkan database dan dokumen. File `.dump` lama tetap diterima untuk pemulihan database saja. Jika proses gagal setelah perubahan database dimulai, aplikasi web tetap berhenti agar tidak melayani data yang tidak utuh; periksa log dan pulihkan backup lengkap yang valid sebelum menyalakan web kembali. Gunakan backup dari versi aplikasi yang sama dan uji pemulihan pada salinan server sebelum pemulihan darurat.

Untuk menjalankan seluruh test secara terpisah dari database operasional:

```powershell
.\scripts\test-school-app.ps1
```

Skrip ini membuat database PostgreSQL sementara khusus test pada jaringan Docker internal yang terpisah dari database operasional, lalu menghapus container test setelah selesai.

## Pengembangan lokal

Kebutuhan pengembangan adalah PHP **8.4.1 atau lebih baru dalam seri 8.x**, ekstensi `pdo_pgsql`, Composer 2, Node.js 24, dan PostgreSQL. Versi PHP minimum mengikuti dependency terkunci di `composer.lock`.

```powershell
Copy-Item .env.example .env
# Atur koneksi PostgreSQL lokal di .env
composer install
npm ci
npm run build
php artisan key:generate
php artisan migrate --seed
php artisan school:user-add admin@sekolah.local ADMIN
php artisan serve
```

Jalankan test hanya dengan database PostgreSQL terpisah bernama `buku_induk_smk_test`; jangan mengarahkan test ke database operasional. Admin pertama dibuat lewat perintah `school:user-add` saat pemasangan; setelah login, akun staf dikelola lewat halaman **Pengelolaan Akun**.

## Cakupan aplikasi

Alur yang tersedia meliputi login berbasis peran, data siswa, impor CSV dengan audit, penyiapan struktur akademik, enrollment, akun operator/wali kelas dan pembatasan akses rombel, serta proses nilai rapor. Nilai memiliki status draft/terkunci/final dan aturan audit.

Halaman Catatan Akademik Siswa kini mencakup hubungan orang tua/wali, ringkasan kehadiran, ekstrakurikuler, PKL, UKK, nilai ijazah, dokumen privat, serta alur verifikasi dan finalisasi kelulusan. Catatan memakai status draft dan finalisasi sesuai modul; akses data wali dan dokumen dibatasi ke izin khusus Admin. Verifikasi Docker dan pemasangan LAN tetap harus dilakukan di PC sekolah yang akan dijadikan server sebelum data nyata dimasukkan.

Sebelum mengisi data siswa sungguhan, sekolah perlu menetapkan PC server, pengelola akun, proses backup/pemulihan, serta kebijakan akses sertifikat dan jaringan. Batasi akses firewall ke jaringan lokal sekolah.
