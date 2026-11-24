# NgampilMas

Sistem Manajemen Peminjaman Alat untuk mengelola inventaris, pengajuan, persetujuan, pengembalian (termasuk sebagian), denda keterlambatan, log aktivitas, dan laporan peminjaman. Aplikasi ditujukan bagi admin, petugas, dan peminjam dalam satu alur kerja yang tercatat. Pembayaran tidak termasuk dalam lingkup aplikasi.

> Status saat ini: backend baru menyediakan migrasi database dan model Eloquent; route API, controller, autentikasi Sanctum, dan antarmuka frontend belum diimplementasikan. Fitur di bawah ditandai **(rencana)** sampai kodenya tersedia.

## Fitur

### Admin
- Kelola pengguna, alat, dan kategori **(rencana)**
- Kelola data peminjaman dan pengembalian **(rencana)**
- Lihat log aktivitas **(rencana)**

### Petugas
- Menyetujui atau menolak pengajuan peminjaman **(rencana)**
- Memantau dan mencatat pengembalian, termasuk pengembalian sebagian **(rencana)**
- Mencetak laporan peminjaman **(rencana)**

### Peminjam
- Melihat daftar alat yang tersedia **(rencana)**
- Mengajukan peminjaman **(rencana)**
- Mengembalikan alat **(rencana)**

Fondasi yang sudah ada di backend: skema database, model Eloquent beserta relasi, soft delete, scope `tersedia` dan `terlambat`, serta helper pencatatan log `LogAktifitas::catat()`.

## Tech Stack

| Bagian | Teknologi |
| --- | --- |
| Backend | PHP ^8.3, Laravel ^13.17, Eloquent, MySQL |
| Autentikasi | Guard session bawaan Laravel; Sanctum belum terpasang **(rencana)** |
| Pengujian backend | Pest, Laravel Pint |
| Frontend | Next.js 16.4.6 (App Router), React 19.3, TypeScript |
| UI & data frontend | Tailwind CSS 4, shadcn/ui + Base UI, TanStack Query, TanStack Table, axios, react-hook-form, zod, Recharts, Sonner |

## Struktur Database

| Tabel | Fungsi |
| --- | --- |
| `users` | Akun pengguna dengan role `admin`/`petugas`/`peminjam`, status aktif, dan soft delete |
| `kategoris` | Kategori alat |
| `alats` | Inventaris alat: kode, stok total/tersedia, kondisi, tarif denda per hari |
| `peminjamans` | Header peminjaman: peminjam, petugas, tanggal, status, keperluan |
| `detail_peminjamans` | Rincian alat per peminjaman, jumlah, dan jumlah yang sudah dikembalikan |
| `pengembalians` | Catatan pengembalian per detail: jumlah, kondisi, hari terlambat, denda |
| `log_aktifitas` | Jejak aktivitas pengguna (aksi, modul, data lama/baru, IP) |
| `sessions` | Sesi login Laravel |
| `password_reset_tokens` | Token reset password Laravel |
| `cache` | Cache aplikasi berbasis database |
| `cache_locks` | Lock cache Laravel |
| `jobs` | Antrean job Laravel |
| `job_batches` | Batch job Laravel |
| `failed_jobs` | Job yang gagal |

## Instalasi

### Prasyarat
- PHP 8.3+ dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2.x
- MySQL 8+
- Node.js 20+ dan npm
- Git

### 1. Clone repositori
```bash
git clone https://github.com/Rfn18/NgampilMas.git
cd NgampilMas
```

### 2. Setup backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database pada `backend/.env`:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ngampilmas_db
DB_USERNAME=root
DB_PASSWORD=
```

Buat database lalu jalankan migrasi:
```bash
mysql -u root -p -e "CREATE DATABASE ngampilmas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
```

Seed data (opsional; seeder masih bawaan Laravel, lihat bagian Akun Demo):
```bash
php artisan db:seed
```

Jalankan backend:
```bash
php artisan serve
```

Backend berjalan di `http://localhost:8000` dan health check di `http://localhost:8000/up`.

### 3. Setup frontend
```bash
cd ../frontend
npm install
```

Pastikan `frontend/.env.local` berisi:
```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Jalankan frontend:
```bash
npm run dev
```

Frontend berjalan di `http://localhost:3000` (saat ini masih halaman default Next.js).

## Akun Demo

TODO: belum ada akun demo. `DatabaseSeeder` masih bawaan Laravel dan belum membuat akun `admin`, `petugas`, atau `peminjam` sesuai skema `users`.

## Ringkasan Endpoint API

Belum ada endpoint API yang aktif. `routes/api.php` belum tersedia, controller belum ada, dan Sanctum belum terpasang. Tabel berikut adalah rencana kontrak endpoint.

| Role | Endpoint (rencana) | Fungsi |
| --- | --- | --- |
| Semua | `POST /api/login`, `POST /api/logout`, `GET /api/me` | Autentikasi dan profil |
| Admin | `/api/users`, `/api/kategori`, `/api/alat` | CRUD data master |
| Admin | `/api/peminjaman`, `/api/pengembalian`, `/api/log-aktivitas` | Monitoring dan audit |
| Petugas | `GET /api/peminjaman` | Melihat pengajuan dan peminjaman |
| Petugas | `POST /api/peminjaman/{id}/approve`, `POST /api/peminjaman/{id}/reject` | Persetujuan pengajuan |
| Petugas | `GET/POST /api/pengembalian` | Mencatat pengembalian dan denda |
| Petugas | `GET /api/laporan` | Laporan peminjaman |
| Peminjam | `GET /api/alat` | Daftar alat tersedia |
| Peminjam | `GET/POST /api/peminjaman` | Melihat dan mengajukan peminjaman |
| Peminjam | `POST /api/pengembalian` | Mengembalikan alat |

## TODO / Rencana Pengembangan

- Menambahkan `routes/api.php`, controller, form request, middleware role, dan policy.
- Memasang serta mengonfigurasi Laravel Sanctum untuk autentikasi API.
- Memperbaiki ketidaksesuaian kode: `Peminjaman::$table` bernilai `peminjaman` sedangkan migrasi membuat `peminjamans`; `User::logAktivitas()` mengacu ke `LogAktivitas` sedangkan kelasnya `LogAktifitas`; typo `Builer` pada `Alat::scopeTersedia()`; nama tabel pada `down()` migrasi (`peminjams`, `detail_peminjamen`).
- Membuat seeder akun demo untuk tiap role.
- Membangun halaman frontend dan integrasi API; saat ini frontend masih halaman default Next.js.
- Menambahkan laporan/cetak dan ekspor data.
- Menambahkan pengujian fitur/endpoint.
- Menyusun kontrak OpenAPI (dependensi `openapi-typescript` sudah ada di frontend).
- Menambahkan notifikasi keterlambatan **(rencana)**.
- Pembayaran tetap di luar lingkup aplikasi.
