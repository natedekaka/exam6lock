# Exam6lock - Sistem Ujian Online

Aplikasi ujian online berbasis PHP dan MySQL untuk sekolah dengan fitur keamanan dan administrasi yang lengkap. Mendukung ujian berbasis komputer (CBT) dengan berbagai fitur keamanan seperti browser lock, device fingerprint, dan IP restriction.

---

## Fitur

### Admin
- **Manajemen Ujian (CRUD)** dengan pengaturan keamanan lengkap
  - Atur judul, deskripsi, durasi, status, jadwal (tanggal mulai/selesai)
  - Opsi acak soal per siswa, acak opsi jawaban
  - Tampilkan review jawaban, tampilkan skor langsung
  - Timer per soal (opsional)
  - Kode rahasia, batasan IP, browser lock, device fingerprint
- **Kelola Soal** — tambah/edit/hapus soal per ujian
  - Mendukung soal pilihan ganda (A-E) dan gambar
  - Kategori soal: Mudah, Sedang, Sulit (dropdown)
  - Timer per soal (opsional, dalam detik)
  - Poin/score per soal
- **Bank Soal Global** — lihat semua soal dari semua ujian, copy soal antar ujian
- **Import Soal Massal** dari CSV / XLSX / XLS
  - Template download tersedia
  - Validasi format otomatis
  - Import ribuan soal sekaligus
- **Ekspor Soal ke PDF** dengan formatting rapi
- **Analytics Dashboard** dengan analisis mendalam
  - Summary statistics: total peserta, rata-rata skor, completion rate, total pelanggaran
  - Distribusi Grade dinamis (berdasarkan KKM yang bisa diatur)
  - Score distribution (0-20, 21-40, 41-60, 61-80, 81-100)
  - Analisis Butir Soal (Top 20 terburuk berdasarkan success rate)
  - Kategori otomatis: Mudah/Sedang/Sulit berdasarkan tingkat keberhasilan
  - Grafik interaktif (Chart.js): grade distribution, score distribution, question analysis
  - Top scorers
  - Students needing remedial (skor < KKM)
  - Violations by hour
  - Recent submissions
  - Export ke Excel (.xls)
- **Rekap Nilai** + Ekspor Excel
  - Filter berdasarkan ujian
  - Statistik nilai (rata-rata, tertinggi, terendah)
  - Detail jawaban per siswa
  - Izin remedi langsung dari tabel
- **Ekspor Jawaban** per Ujian ke Excel — detail jawaban + pelanggaran
- **Monitor Ujian Real-time**
  - Lihat siswa yang sedang mengerjakan
  - Progress ujian per siswa (soal ke berapa)
  - Hapus siswa dari progress (jika ada kendala)
  - Reset ujian siswa (bisa mengerjakan ulang)
- **Manajemen Pelanggaran (Violations)**
  - Deteksi tab switching / Alt-Tab
  - Deteksi pergantian device (device fingerprint)
  - Auto-submit setelah batas pelanggaran
  - Hapus pelanggaran (jika false positive)
- **Profil Sekolah**
  - Nama sekolah
  - Upload logo sekolah
  - Pengaturan warna tema (primer & sekunder)
  - Tampilkan/sembunyikan riwayat nilai
- **Kelola Kelas & Jurusan** — CRUD data kelas dan jurusan
- **Kelola Siswa** — CRUD data siswa, download template CSV, import siswa
- **Pengumuman** — CRUD pengumuman (tipe: umum / per kelas)
- **Kartu Peserta** — lihat & cetak kartu peserta per kelas
- **Manajemen Admin** (Super Admin only) — tambah/edit/hapus admin
- **Audit Log** (Super Admin only) — log aktivitas admin
- **Backup & Restore Database** (Super Admin only)
- **Ganti Password** Admin

### Siswa
- **Registrasi Mandiri** — siswa bisa mendaftar akun sendiri
- **Login** dengan NIS & password (dengan remember token)
- **Dashboard Siswa** setelah login
  - Stat cards: ujian tersedia, sudah dikerjakan, rata-rata nilai, sisa ujian
  - Pengumuman terbaru (filter by kelas)
  - Daftar ujian tersedia (4 teratas) — langsung klik "Mulai"
  - Riwayat 5 nilai terakhir dengan badge skor
- **Top Navbar** (mobile-friendly)
  - Navigasi: Beranda, Ujian, Nilai, Pengumuman
  - Dropdown user (Profil, Ganti Password, Logout)
- **Landing Page** — daftar ujian yang tersedia (dengan filter jadwal & kelas)
  - Guest: "Login untuk Mengerjakan"
  - Login: "Mulai Ujian"
- **Pra-check Ujian**: Jika sudah pernah submit & tidak ada izin remedi → halaman blokir
- **Verifikasi Kode Rahasia** sebelum ujian (jika diaktifkan)
- **Sistem Ujian Interaktif** dengan timer countdown
  - 1 soal per halaman (load cepat)
  - Navigasi grid nomor soal (lompat ke soal tertentu)
  - Indicator warna: abu (belum), hijau (dijawab), biru (aktif)
  - Previous/Next button
  - Auto-save jawaban setiap 30 detik (background)
  - Load jawaban tersimpan jika refresh/halaman crash
  - Timer per-soal (jika diaktifkan)
- **Review Jawaban** setelah submit (jika diaktifkan)
  - Filter: semua, benar saja, salah saja
  - Tampilkan skor per soal
- **Riwayat Nilai** — lihat history ujian sendiri (setelah login, via session)
- **Profil Siswa** — lihat & edit profil
- **Ganti Password** — ubah password sendiri
- **Pengumuman** — daftar pengumuman (filter by kelas)

### Keamanan
- **CSRF Protection** untuk semua form & API
- **Pengecekan Ganda** agar siswa tidak mengerjakan dua kali
- **Auto-submit** jika pelanggaran melebihi batas
- **Log Pelanggaran** tab switching dengan detail waktu
- **Race Condition Protection** — transaksi database dengan locking
- **Temporary Table** untuk auto-save tanpa mempengaruhi data final
- **Device Fingerprinting** mendeteksi pergantian perangkat
- **IP Address Validation** membatasi akses dari IP tertentu
- **Audit Trail** — log semua aktivitas admin

### Performa & Caching
- **Redis Cache** — caching daftar ujian aktif & konfigurasi sekolah (fallback graceful jika Redis tidak tersedia)
- Mobile-first design untuk load cepat di HP
- Pagination 1 soal per halaman
- Database indexes untuk query cepat
- JSON-based answer storage
- PHP-FPM + Apache dengan tuning performa

---

## Requirements

- **PHP 8.0+** (direkomendasikan 8.2)
- **MySQL 8.0+** atau MariaDB 10.4+
- **Web Server**: Apache/Nginx (atau gunakan Docker/Podman)
- **Redis 7+** (opsional, untuk caching)
- **Docker** atau **Podman** (opsional, direkomendasikan untuk development)

---

## Struktur Direktori

```
exam6lock/
├── admin/                          # Panel admin
│   ├── assets/
│   │   ├── css/admin.css           # CSS admin sidebar & layout
│   │   └── js/admin.js             # JS admin (toggle sidebar, dll)
│   ├── partials/
│   │   └── sidebar.php             # Sidebar navigasi admin
│   ├── index.php                   # Dashboard admin (manajemen ujian)
│   ├── login.php                   # Login admin
│   ├── logout.php                  # Logout admin
│   ├── tambah_soal.php             # Tambah/edit soal per ujian
│   ├── bank_soal.php               # Bank Soal Global (copy soal antar ujian)
│   ├── import_soal.php             # Import soal dari CSV/XLSX/XLS
│   ├── download_template.php       # Download template CSV
│   ├── ekspor_soal_pdf.php         # Ekspor soal ke PDF
│   ├── kelola_kelas.php            # Kelola kelas & jurusan
│   ├── kelola_siswa.php            # Manajemen data siswa (CRUD + import)
│   ├── rekap_nilai.php             # Rekap nilai + izin remedi
│   ├── ekspor_excel.php            # Ekspor rekap nilai ke Excel
│   ├── ekspor_jawaban.php          # Ekspor detail jawaban per ujian ke Excel
│   ├── detail_jawaban.php          # Detail jawaban siswa (admin view)
│   ├── analytics.php               # Analytics dashboard (Chart.js)
│   ├── monitor_ujian.php           # Monitor ujian real-time
│   ├── kartu_peserta.php           # Lihat kartu peserta
│   ├── kartu_peserta_cetak.php     # Cetak kartu peserta
│   ├── pengumuman.php              # CRUD pengumuman
│   ├── profil_sekolah.php          # Pengaturan profil sekolah
│   ├── manage_users.php            # Manajemen admin (super admin only)
│   ├── audit_log.php               # Log aktivitas admin (super admin only)
│   ├── backup_restore.php          # Backup & restore database
│   ├── ganti_password.php          # Ganti password admin
│   └── backfill_detail_jawaban.php # Utility: backfill detail jawaban
├── siswa/                          # Area siswa
│   ├── assets/css/siswa.css        # CSS khusus siswa (navbar, dashboard)
│   ├── partials/navbar.php         # Top navbar siswa
│   ├── index.php                   # Redirect ke dashboard
│   ├── login.php                   # Login siswa
│   ├── register.php                # Registrasi mandiri siswa
│   ├── logout.php                  # Logout siswa
│   ├── dashboard.php               # Dashboard siswa
│   ├── profil.php                  # Profil siswa
│   ├── ganti_password.php          # Ganti password
│   ├── pengumuman.php              # Daftar pengumuman
│   ├── detail_jawaban.php          # Review jawaban siswa
│   └── .htaccess                   # Security: disable directory listing
├── api/                            # API endpoint
│   ├── index.php                   # Redirect (cegah listing)
│   ├── submit_jawaban.php          # Submit & auto-save jawaban
│   └── get_ip.php                  # Get IP client (JSON)
├── config/                         # Konfigurasi
│   ├── database.php                # Koneksi database
│   ├── db_helper.php               # Database helper (transaction, locking)
│   ├── init_sekolah.php            # Inisialisasi tabel & konfigurasi sekolah
│   ├── redis_helper.php            # Redis caching helper
│   ├── audit_helper.php            # Audit logging helper
│   ├── performance/
│   │   ├── php-tuning.ini          # PHP performance tuning
│   │   ├── fpm-pool.conf           # PHP-FPM pool config
│   │   └── apache-tuning.conf      # Apache MPM event tuning
│   └── apache/
│       └── fpm-site.conf           # Apache virtual host (PHP-FPM)
├── vendor/                         # Frontend libraries
│   ├── bootstrap/                  # Bootstrap 5
│   ├── bootstrap-icons/            # Bootstrap Icons
│   ├── chart.js/                   # Chart.js 4 (grafik analytics)
│   └── fonts/
│       ├── poppins/                # Font Poppins
│       └── inter/                  # Font Inter (fallback)
├── migrations/                     # Database migrations
│   ├── 01_base_tables.sql          # Tabel dasar (admin_users, soal, hasil_ujian)
│   ├── 06_performance_indexes.sql  # Index performa
│   ├── 07_add_kategori_timer_soal.sql # Kategori & timer per soal
│   ├── 08_increase_max_violations.sql  # Perbesar batas pelanggaran
│   ├── 09_new_features.sql         # jurusan, kelas, siswa, ujian_kelas, izin_remedi, pengumuman, audit_log
│   └── 10_siswa_password_change.sql    # Password change feature
├── backup_db/                      # Database backup
│   ├── backup_2026-06-11_21-23.sql
│   └── ujian_online (1-5-26).sql
├── uploads/                        # File upload
│   ├── index.php                   # Prevent listing
│   └── *.png                       # Logo sekolah & gambar soal
├── index.php                       # Landing page (hero + daftar ujian)
├── ujian.php                       # Halaman ujian siswa (soal, timer, submit)
├── review.php                      # Review jawaban setelah submit
├── riwayat.php                     # Riwayat nilai (wajib login, via session)
├── rekap_nilai.php                 # Rekap nilai publik
├── petunjuk_siswa.md               # Petunjuk ujian untuk siswa
├── petunjuk_siswa.pdf              # Petunjuk ujian (PDF)
├── docker-compose.yml              # Docker/Podman Compose
├── Dockerfile                      # PHP 8.2 FPM + Apache
├── docker-entrypoint.sh            # Entrypoint container
└── .gitignore
```

---

## Cara Install

### ⭐ Cara Termudah: Docker / Podman (Rekomendasi)

Tidak perlu install PHP, MySQL, atau Apache secara manual.

#### Yang Perlu Disiapkan

| Tools | Download |
|-------|----------|
| **Docker** atau **Podman** | https://docker.com / https://podman.io |
| **Git** (opsional) | https://git-scm.com/downloads |

#### Langkah 1: Clone atau Download

```bash
git clone https://github.com/natedekaka/exam6lock.git
cd exam6lock
```

Atau download ZIP, ekstrak, lalu buka terminal di folder `exam6lock`.

#### Langkah 2: Jalankan Aplikasi

```bash
docker compose up -d
# atau
podman compose up -d
```

Tunggu 1-2 menit sampai semua container siap.

#### Langkah 3: Setup Database

Database akan otomatis terbuat dari file di folder `migrations/` saat container pertama kali dijalankan.

Jika ingin import data contoh:

1. Buka http://localhost:9091 (phpMyAdmin)
2. Login:
   - **Server**: `db`
   - **Username**: `root`
   - **Password**: `rootpass`
3. Di panel kiri, klik **`ujian_online`**
4. Klik tab **"Import"**
5. Pilih file `backup_db/ujian_online (1-5-26).sql` atau `backup_db/backup_2026-06-11_21-23.sql`
6. Klik **"Go"**

#### Langkah 4: Akses Aplikasi

| Halaman | URL | Login |
|---------|-----|-------|
| **Aplikasi Utama** | http://localhost:9090 | - |
| **Admin Panel** | http://localhost:9090/admin/login.php | `admin` / `admin123` |
| **phpMyAdmin** | http://localhost:9091 | root / rootpass |

---

### ⚙️ Cara Manual (XAMPP / LAMP / Laragon)

#### Langkah 1: Letakkan File

| OS | Folder |
|----|--------|
| **Windows (XAMPP)** | `C:\xampp\htdocs\exam6lock` |
| **Windows (Laragon)** | `C:\laragon\www\exam6lock` |
| **Linux (LAMP)** | `/var/www/html/exam6lock` |

#### Langkah 2: Setup Database

1. Buat database **`ujian_online`** (collation: `utf8mb4_general_ci`)
2. Import file migration dari folder `migrations/` secara berurutan:
   - `01_base_tables.sql`
   - `06_performance_indexes.sql`
   - `07_add_kategori_timer_soal.sql`
   - `08_increase_max_violations.sql`
   - `09_new_features.sql`
   - `10_siswa_password_change.sql`
3. Atau import file backup dari `backup_db/`

#### Langkah 3: Konfigurasi Database

Buka `config/database.php`:

```php
$host = 'localhost';
$user = 'root';
$password = '';           // XAMPP: kosong
$database = 'ujian_online';
$port = '3306';
```

| Software | Username | Password |
|----------|----------|----------|
| **XAMPP** | `root` | `(kosong)` |
| **Laragon** | `root` | `(kosong)` |
| **LAMP Ubuntu** | `root` | `(password MySQL Anda)` |
| **MAMP** | `root` | `root` |

#### Langkah 4: Permission Folder

```bash
chmod -R 777 exam6lock/uploads/
```

#### Langkah 5: Akses Aplikasi

Buka **http://localhost/exam6lock**

---

## Akun Default

| Role | Username/NIS | Password | Keterangan |
|------|-------------|----------|------------|
| Admin | `admin` | `admin123` | Segera ganti setelah login pertama |
| Siswa | (data di database) | `siswa123` (default) | Atur di Kelola Siswa |

> ⚠️ **Penting**: Segera ubah password default admin setelah login pertama.

---

## Cara Penggunaan

### Admin

#### Dashboard Admin
Setelah login, admin melihat dashboard utama (daftar ujian). Dari sidebar, akses menu:
- **Dashboard** — daftar ujian, tambah/edit/hapus ujian
- **Kelola Soal** — tambah/edit/hapus soal per ujian
- **Bank Soal Global** — lihat & copy soal antar ujian
- **Import Soal** — import massal dari CSV/XLSX
- **Rekap Nilai** — lihat & ekspor nilai, beri izin remedi
- **Analytics** — analisis butir soal, distribusi grade, grafik
- **Monitor Ujian** — pantau ujian real-time
- **Kartu Peserta** — lihat & cetak kartu peserta
- **Kelola Kelas** — kelola data kelas & jurusan
- **Kelola Siswa** — kelola data siswa
- **Profil Sekolah** — atur logo, warna tema, nama sekolah
- **Pengumuman** — kelola pengumuman
- **Ganti Password** — ubah password admin

> Menu **Kelola Admin**, **Audit Log**, dan **Backup & Restore** hanya untuk Super Admin.

#### Manajemen Ujian
- Isi form: judul, deskripsi, durasi, status, jadwal, acak soal, acak opsi, review, dll.
- **Pengaturan Keamanan**: kode ujian, batasan IP, browser lock, device fingerprint
- **Toggle status** langsung dari daftar (aktif/nonaktif)

#### Kelola Soal
- Dukung pilihan ganda A-E, gambar soal, kategori (Mudah/Sedang/Sulit), timer per soal, poin
- **Bank Soal Global**: lihat semua soal, copy ke ujian lain
- **Import**: upload CSV/XLSX (template bisa didownload)
- **Ekspor PDF**: download soal + kunci jawaban

#### Monitoring & Analisis
- **Monitor Ujian**: lihat progres siswa real-time, reset/hapus siswa
- **Analytics**: atur KKM, lihat distribusi grade, analisis butir soal, top scorers, siswa remedial
- **Rekap Nilai**: filter per ujian, lihat skor + pelanggaran, ekspor Excel, beri izin remedi
- **Ekspor Jawaban**: download detail jawaban per ujian format Excel

### Siswa

#### Alur Ujian
1. **Landing Page** → lihat daftar ujian
2. **Login** (atau Register jika belum punya akun)
3. **Dashboard** → lihat statistik, pengumuman, ujian tersedia
4. Klik **"Mulai Ujian"** pada kartu ujian
5. Jika sudah pernah submit → cek izin remedi (blokir atau lanjut)
6. Masukkan **Kode Ujian** (jika diaktifkan)
7. Konfirmasi identitas → klik **"Mulai Ujian"**
8. Kerjakan soal (1 per halaman, auto-save tiap 30 detik)
9. **Submit** → nilai muncul langsung
10. **Review** (jika diaktifkan) → filter benar/salah

---

## Pengaturan Keamanan Ujian

| Fitur | Deskripsi | Rekomendasi |
|-------|-----------|-------------|
| **Kode Ujian** | Kode rahasia yang harus dimasukkan siswa | Ujian penting |
| **Batasan IP** | Batasi akses dari IP tertentu (pisahkan koma) | Ujian di lab sekolah |
| **Browser Lock** | Deteksi tab switch/copy-paste, auto-submit setelah X pelanggaran | Ujian formal |
| **Device Fingerprint** | Deteksi pergantian device/browser di tengah ujian | Cegah kecurangan |

---

## Teknologi

- **Frontend**:
  - Bootstrap 5 (CSS Framework)
  - Bootstrap Icons (Icon library)
  - Vanilla JavaScript (ES6+)
  - Font Poppins (Google Fonts)
  - Chart.js 4 (Data visualization)

- **Backend**:
  - PHP 8.2 Native (tanpa framework)
  - MySQL 8.0 (database)
  - Redis 7 (caching, opsional)
  - JSON untuk penyimpanan jawaban

- **Container**:
  - Docker / Podman
  - PHP 8.2 FPM + Apache
  - MySQL 8.0
  - Redis 7 Alpine
  - phpMyAdmin latest

- **Tools**:
  - Git untuk version control

---

## Troubleshooting

### 1. Error "Koneksi database gagal"
- Cek apakah container database sudah running (`docker ps`)
- Cek konfigurasi di `config/database.php`:
  - Docker: host = `db`, password = `rootpass`
  - XAMPP: host = `localhost`, password = `(kosong)`

### 2. Tabel tidak ditemukan
- Database belum diimport. Import file dari `migrations/` atau `backup_db/`.

### 3. Gambar tidak muncul
- Pastikan folder `uploads/` memiliki permission 777

### 4. Auto-save tidak berfungsi
- Cek console browser (F12) untuk error JavaScript

### 5. Container tidak bisa start
```bash
docker compose logs app
docker compose logs db
docker compose down
docker compose up -d
```

---

## Lisensi

MIT License

Copyright (c) 2026 Exam6lock - Sistem Ujian Online

Dibenarkan untuk menggunakan, memodifikasi, dan mendistribusikan aplikasi ini dengan atau tanpa modifikasi untuk keperluan komersial maupun non-komersial.

---

## Kontak & Support

- **Repository**: https://github.com/natedekaka/exam6lock
- **Issues**: https://github.com/natedekaka/exam6lock/issues
- **Email**: natedekaka@gmail.com

---

**Dibuat dengan ❤️ untuk dunia pendidikan Indonesia — MGMP Informatika SMAN 6 Cimahi**
