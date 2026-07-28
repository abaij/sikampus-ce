# SIAK Backend

Backend **SIAK (Sistem Informasi Akademik)** — sebuah REST API berbasis Laravel 12 untuk sistem
informasi akademik perguruan tinggi, dilengkapi modul **PMB (Penerimaan Mahasiswa Baru)** yang
terintegrasi di dalamnya.

## 1. Gambaran Sekilas

SIAK Backend menyediakan API yang dikonsumsi oleh aplikasi frontend terpisah (mahasiswa, dosen, dan
panel admin), serta panel web berbasis Blade + Livewire yang ringan khusus untuk superadmin (edit
konfigurasi environment, memicu migrasi database, dan pengujian upload file).

Cakupan domain yang didukung antara lain:

- **Manajemen akademik** — Fakultas, Program Studi, Kurikulum, Mata Kuliah, Kelas, Jadwal, KRS
  (Kartu Rencana Studi), dan penilaian (Nilai, Revisi Nilai, Konversi Nilai).
- **RPS (Rencana Pembelajaran Semester)** — pemetaan CPL, CPMK, Sub-CPMK, dan rencana pembelajaran
  per dosen/kelas.
- **Keuangan** — tagihan, rincian tagihan, pembayaran, komponen biaya, struktur biaya, dan
  keringanan biaya.
- **Tugas akhir** — alur Tugas Akhir → Ujian Sidang → Yudisium → Wisuda.
- **PMB (Penerimaan Mahasiswa Baru)** — modul pendaftaran mahasiswa baru yang berdiri sendiri
  (auth & sesi terpisah dari sistem utama), dengan prefix rute `/pmb`.
- **Autentikasi & otorisasi berlapis** — role & permission berbasis [Spatie Permission] untuk akses
  panel/modul, kolom `role` legacy untuk tipe akun (admin, dosen, mahasiswa), serta scoping data
  per fakultas/program studi untuk admin.
- **Integrasi sistem eksternal** — endpoint khusus (mis. untuk Siska) yang diakses via API key,
  bukan token Sanctum biasa.

## 2. Stack yang Digunakan

**Backend**

- [PHP](https://www.php.net/) ^8.2
- [Laravel](https://laravel.com/) ^12.0
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — autentikasi API (SPA cookie & bearer token)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) — role & permission
- [Livewire](https://livewire.laravel.com/) ^4.3 — panel admin superadmin berbasis Blade
- [PestPHP](https://pestphp.com/) — testing framework
- [Laravel Pint](https://laravel.com/docs/pint) — code style / formatter
- [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) — import/export Excel
- [DomPDF](https://github.com/dompdf/dompdf) — generate PDF
- [Intervention Image](https://image.intervention.io/) — pemrosesan gambar (mis. KTM mahasiswa)

**Frontend (panel Blade minimal)**

- [Vite](https://vitejs.dev/) ^7
- [Tailwind CSS](https://tailwindcss.com/) ^4
- [Tom Select](https://tom-select.js.org/) — dropdown/select interaktif
- [Sonner](https://sonner.emilkowal.ski/) — notifikasi toast

**Database & Infrastruktur**

- MySQL (database utama & database testing `siak_testing`)
- Redis (opsional, untuk cache/queue)
- Queue driver: database
- Session driver: database

> Catatan: Aplikasi ini murni backend/API. Untuk antarmuka pengguna mahasiswa/dosen/admin utama
> digunakan aplikasi frontend terpisah yang mengonsumsi API ini (lihat konfigurasi `FRONTEND_URL`).

## 3. Cara Instal Melalui GitHub

### Prasyarat

- PHP >= 8.2 beserta ekstensi yang dibutuhkan Laravel
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) & npm
- MySQL
- (Opsional) Redis

### Langkah instalasi

```bash
git clone https://github.com/abaij/sikampus-opensource.git
cd sikampus-opensource
```

```bash
composer install
```

```bash
npm install
```

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi di file `.env`, minimal:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — koneksi database utama
- `FRONTEND_URL` — URL aplikasi frontend (default `http://localhost:3000`)

```bash
php artisan migrate
```

Jalankan aplikasi (server + queue worker + log tail + Vite, sekaligus):

```bash
composer dev
```

Aplikasi backend akan berjalan di `http://localhost:8000` (default `php artisan serve`).

## 4. Cara Instal Melalui Download Source Code

Jika tidak menggunakan `git clone`, unduh source code secara manual dari GitHub:

1. Buka halaman repository [sikampus-opensource](https://github.com/abaij/sikampus-opensource).
2. Klik tombol **Code** → **Download ZIP**.
3. Ekstrak file ZIP tersebut ke direktori kerja pilihan Anda.
4. Buka terminal, arahkan ke folder hasil ekstrak, misalnya:

```bash
cd path/ke/folder/sikampus-opensource-main
```

5. Lanjutkan dengan langkah instalasi yang sama seperti instalasi melalui GitHub (poin 3 di atas),
   mulai dari `composer install`:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

6. Sesuaikan konfigurasi `.env` (database, `FRONTEND_URL`, dll), lalu jalankan migrasi:

```bash
php artisan migrate
```

7. Jalankan aplikasi:

```bash
composer dev
```

## Menjalankan Test

Test menggunakan Pest dan berjalan terhadap database MySQL bernama `siak_testing` (bukan SQLite).
Pastikan database tersebut sudah dibuat sebelum menjalankan test.

```bash
composer test
```

atau

```bash
php artisan test
```

## Format / Lint Kode

```bash
vendor/bin/pint          # perbaiki otomatis
vendor/bin/pint --test   # cek saja, tanpa mengubah file
```
