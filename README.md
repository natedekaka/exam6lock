# Exam6 - Sistem Ujian Online

Aplikasi ujian online berbasis PHP dan MySQL untuk sekolah dengan fitur keamanan dan administrasi yang lengkap. Mendukung ujian berbasis komputer (CBT) dengan berbagai fitur keamanan.

## Kompatibilitas

### Platform yang Didukung
- **Desktop**: Chrome, Firefox, Safari, Edge (versi terbaru)
- **Mobile**: 
  - **Android**: Android 7.0 (Nougat) ke atas
  - **iOS**: iOS 12 ke atas (termasuk iPhone XR, iPhone 11, dll)
- **Browser Mobile**: Chrome, Safari, Firefox

### Fitur Mobile
- Tampilan responsive mobile-first design
- Interface seperti aplikasi native
- Navigasi soal yang mudah di HP
- Load cepat untuk koneksi lambat

---

## Fitur

### Admin
- **Manajemen Ujian (CRUD)** dengan pengaturan keamanan lengkap
  - Atur judul, deskripsi, durasi, dan status ujian
  - Opsi acak soal per siswa
  - Tampilkan/nilai ulang hasil ujian
- **Bank Soal** (tambah/edit/hapus soal dengan gambar)
  - Mendukung soal pilihan ganda dan essay
  - Kategori soal: Mudah, Sedang, Sulit (dropdown)
  - Timer per soal (opsional)
  - Poin/score per soal
- **Import Soal Massal** dari Excel/CSV
  - Template download tersedia (format: soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, kunci, poin, kategori)
  - Validasi format otomatis
  - Import ribuan soal sekaligus
- **Ekspor Soal ke PDF** dengan formatting rapi
- **Analytics Dashboard** dengan analisis mendalam
  - Distribusi Grade dinamis (berdasarkan KKM)
  - Analisis Butir Soal (Top 20 terburuk berdasarkan success rate)
  - Kategori otomatis: Mudah/Sedang/Sulit berdasarkan tingkat keberhasilan
  - Grafik interaktif (Chart.js): grade distribution, score distribution, question analysis
  - Daftar siswa yang perlu remedial (skor < KKM)
  - Top scorers
  - Export ke Excel (.xls) kompatibel LibreOffice/WPS Office
- **Rekap Nilai** dengan ekspor Excel
  - Filter berdasarkan ujian dan kelas
  - Statistik nilai (rata-rata, tertinggi, terendah)
  - Detail jawaban per siswa
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
- **Pengaturan Keamanan Ujian**:
  - Kode Rahasia (Exam Code) - siswa wajib masukkan kode
  - Batasan IP Address - hanya IP tertentu yang bisa akses
  - Browser Lock - deteksi tab switching / copy-paste
  - Device Fingerprint - deteksi pergantian device/browser

### Siswa
- **Dashboard Siswa** setelah login
  - Stat cards: ujian tersedia, sudah dikerjakan, rata-rata nilai, sisa ujian
  - Pengumuman terbaru (filter by kelas)
  - Daftar ujian tersedia (4 teratas) — langsung klik "Mulai"
  - Riwayat 5 nilai terakhir dengan badge skor
- **Top Navbar** (mobile-friendly)
  - Navigasi: Beranda, Ujian, Nilai, Pengumuman
  - Dropdown user (Profil, Ganti Password, Logout) — selalu terlihat di HP
- **Landing Page** dengan daftar ujian yang tersedia
  - Filter ujian berdasarkan status dan jadwal
  - Guest: "Login untuk Mengerjakan" → login → dashboard
  - Login: "Mulai Ujian" → langsung ke halaman ujian
- **Pra-check Ujian**: Jika sudah pernah submit & tidak ada izin remedi → halaman blokir dengan tombol ke Dashboard
- **Izin Remedi**: Admin beri izin → siswa bisa ujian ulang (otomatis lewati pra-check)
- **Verifikasi Kode Rahasia** sebelum ujian (jika diaktifkan) — di-reset setiap kali ganti ujian
- **Sistem Ujian Interaktif** dengan timer countdown
  - 1 soal per halaman (load cepat)
  - Navigasi grid nomor soal (lompat ke soal tertentu)
  - Indicator warna: abu (belum), hijau (dijawab), biru (aktif)
  - Previous/Next button untuk navigasi
  - Auto-save jawaban setiap 30 detik (background)
  - Load jawaban tersimpan jika refresh/halaman crash
  - Dukungan soal essay (isian singkat)
- **Review Jawaban** setelah submit (jika diaktifkan guru)
  - Lihat soal, jawaban siswa, dan kunci jawaban
  - Filter: semua, benar saja, salah saja
  - Tampilkan skor per soal
- **Riwayat Nilai** berdasarkan NIS
  - Lihat history semua ujian yang sudah dikerjakan
  - Detail nilai dan skor (link ke halaman review jawaban)

### Keamanan
- **CSRF Protection** untuk semua API endpoint
- **Pengecekan Ganda** agar siswa tidak mengerjakan dua kali
- **Auto-submit** jika pelanggaran browser melebihi batas
- **Log Pelanggaran** tab switching dengan detail waktu
- **Race Condition Protection** mencegah double submission saat submit bersamaan
- **Transaksi Database dengan Locking** untuk integritas data
- **Temporary Table** untuk auto-save tanpa mempengaruhi data final
- **Device Fingerprinting** mendeteksi pergantian perangkat
- **IP Address Validation** membatasi akses dari IP tertentu

### Performa
- Mobile-first design untuk load cepat di HP
- Pagination 1 soal per halaman (mengurangi beban server)
- Render JavaScript yang dioptimasi (lazy loading)
- Inisialisasi token di background (tidak blocking UI)
- Database indexes untuk query cepat
- JSON-based answer storage (efisien untuk jawaban kompleks)
- Session-less design (menggunakan token-based authentication)

---

## Requirements

- **PHP 8.0+** (direkomendasikan 8.2)
- **MySQL 8.0+** atau MariaDB 10.4+
- **Web Server**: Apache/Nginx (atau gunakan Podman)
- **Podman** (opsional, direkomendasikan untuk development)

---

## Struktur Direktori

```
exam6lock/
├── admin/                      # Panel admin
│   ├── assets/                 # CSS & JS admin (sidebar, dashboard)
│   ├── partials/               # Partial templates (sidebar.php)
│   ├── index.php              # Dashboard admin
│   ├── login.php              # Login admin
│   ├── logout.php             # Logout admin
│   ├── tambah_soal.php        # Tambah/edit soal (dropdown kategori: Mudah/Sedang/Sulit)
│   ├── manage_users.php        # Manajemen pengguna
│   ├── kelola_siswa.php        # Manajemen data siswa (+ download template CSV)
│   ├── rekap_nilai.php         # Rekap nilai & ekspor (+ izin remedi)
│   ├── monitor_ujian.php       # Monitor ujian real-time
│   ├── izin_remedi.php         # Atur izin remedi siswa
│   ├── profil_sekolah.php      # Pengaturan profil sekolah
│   ├── analytics.php           # Analytics Dashboard (analisis butir soal, distribusi grade dinamis)
│   ├── import_soal.php         # Import soal dari Excel/CSV
│   ├── ekspor_excel.php        # Ekspor nilai ke Excel
│   ├── ekspor_soal_pdf.php     # Ekspor soal ke PDF
│   ├── backup_restore.php      # Backup & restore database
│   ├── audit_log.php           # Log aktivitas admin
│   └── detail_jawaban.php      # Detail jawaban siswa (admin view)
├── siswa/                      # Area siswa
│   ├── assets/css/siswa.css    # CSS khusus siswa (navbar, dashboard, dll)
│   ├── partials/navbar.php     # Top navbar (shared)
│   ├── dashboard.php           # Dashboard siswa (stat cards, pengumuman, ujian, nilai)
│   ├── login.php               # Login siswa (dengan remember token)
│   ├── logout.php              # Logout siswa
│   ├── index.php               # Alihkan ke dashboard
│   ├── profil.php              # Profil siswa
│   ├── ganti_password.php      # Ganti password (wajib jika password_change_required)
│   ├── pengumuman.php          # Daftar pengumuman
│   ├── register.php            # Registrasi siswa
│   └── detail_jawaban.php      # Review jawaban siswa setelah ujian
├── api/                        # API endpoint
│   ├── index.php               # API router
│   └── submit_jawaban.php      # Submit & auto-save jawaban siswa
├── config/                     # Konfigurasi
│   ├── database.php            # Konfigurasi database
│   ├── init_sekolah.php        # Inisialisasi sekolah & tabel
│   └── db_helper.php           # Database helper functions
├── vendor/                     # Library (Bootstrap 5.3.3, Bootstrap Icons 1.11.3)
├── uploads/                    # File upload (logo, gambar soal)
├── migrations/                 # Database migrations
│   ├── 06_performance_indexes.sql
│   ├── 07_add_kategori_timer_soal.sql
│   ├── 08_increase_max_violations.sql
│   ├── 09_new_features.sql     # Tabel: jurusan, kelas, siswa, ujian_kelas, izin_remedi, pengumuman, audit_log
│   └── 10_siswa_password_change.sql
├── backup_db/                  # Database backup
│   └── ujian_online.sql        # Full database backup
├── index.php                   # Landing page (list ujian + hero)
├── ujian.php                   # Halaman ujian siswa (pre-check, kode, identitas, soal, submit)
├── review.php                  # Review jawaban setelah submit
├── riwayat.php                 # Riwayat nilai siswa
├── petunjuk_siswa.pdf          # Petunjuk pengerjaan ujian (PDF)
├── docker-compose.yml          # Konfigurasi Podman
└── README.md                   # Dokumentasi ini
```

---

## Cara Install

### ⭐ Cara Termudah: Podman (Rekomendasi)

Tidak perlu install PHP, MySQL, atau Apache. Cukup jalankan 3 perintah.

#### Yang Perlu Disiapkan

| Tools | Download |
|-------|----------|
| **Podman** | https://podman.io/getting-started/installation |
| **Git** (opsional) | https://git-scm.com/downloads |

#### Langkah 1: Clone atau Download

```bash
git clone https://github.com/natedekaka/exam6lock.git
cd exam6lock
```

Atau download ZIP dari https://github.com/natedekaka/exam6lock, ekstrak, lalu buka terminal di folder `exam6lock`.

#### Langkah 2: Jalankan Aplikasi

```bash
podman compose up -d
```

Tunggu 1-2 menit sampai semua container siap.

#### Langkah 3: Setup Database (OTOMATIS)

✅ **Tidak perlu buat database manual!** Cukup import lewat phpMyAdmin:

> 💡 **Tips biar cepat:** File SQL bisa di-zip dulu sebelum import. phpMyAdmin bisa baca file `.zip` atau `.gz` langsung!

1. **Compress file** (opsional — tapi bikin import jauh lebih cepat):
   ```bash
   gzip -k backup_db/ujian_online.sql
   #   backup_db/ujian_online.sql.gz  (ukuran ~160KB dari ~2.8MB)
   ```
2. Buka http://localhost:8025
3. Login phpMyAdmin:
   - **Server**: `db`
   - **Username**: `root`  
   - **Password**: `rootpass`
4. Di panel kiri, klik **`ujian_online`**
5. Klik tab **"Import"** di menu atas
6. Klik **"Choose File"**, pilih file:
   - **Biasa**: `backup_db/ujian_online.sql`
   - **Lebih cepat**: `backup_db/ujian_online.sql.gz` (compress dulu)
7. Scroll ke bawah, klik **"Go"**
8. ✅ Selesai! Semua tabel terbuat otomatis.

> 🔥 **Sudah termasuk data contoh**: admin, ujian, soal, dan siswa bisa langsung dipakai.

#### Langkah 4: Akses Aplikasi

| Halaman | URL | Login |
|---------|-----|-------|
| **Aplikasi Utama** | http://localhost:8024 | - |
| **Admin Panel** | http://localhost:8024/admin/login.php | `admin` / `admin123` |
| **phpMyAdmin** | http://localhost:8025 | root / rootpass |

#### Langkah 5: Ganti Password Admin

Login admin → klik **Manajemen User** → ganti password `admin123`.

---

### ⚙️ Cara Manual (XAMPP / LAMP / Laragon)

Untuk yang sudah punya web server sendiri.

#### Langkah 1: Letakkan File

| OS | Folder |
|----|--------|
| **Windows (XAMPP)** | `C:\xampp\htdocs\exam6lock` |
| **Windows (Laragon)** | `C:\laragon\www\exam6lock` |
| **Linux (LAMP)** | `/var/www/html/exam6lock` |

Bisa dengan git clone atau download ZIP dan ekstrak ke folder di atas.

#### Langkah 2: Setup Database (via phpMyAdmin)

1. **Buka** http://localhost/phpmyadmin
2. Klik tab **"Databases"** di menu atas
3. **Buat database baru**:
   - Nama: `ujian_online`
   - Collation: `utf8mb4_general_ci`
   - Klik **"Create"**
4. **Klik database** `ujian_online` di panel kiri
5. Klik tab **"Import"** di menu atas
6. Klik **"Choose File"** → pilih file:
   - **Biasa**: `backup_db/ujian_online.sql`
   - **Lebih cepat**: `backup_db/ujian_online.sql.gz` (compress dulu: `gzip -k backup_db/ujian_online.sql`)
7. Scroll ke bawah, klik **"Go"**
8. ✅ Database siap!

> ⚠️ **Error "database exists"?** Lewati langkah 3, langsung klik database `ujian_online` di panel kiri.

#### Langkah 3: Konfigurasi Koneksi Database

Buka file `config/database.php`, sesuaikan bagian ini:

```php
// XAMPP default (tinggal copas):
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

**Linux/Mac:**
```bash
chmod -R 777 exam6lock/uploads/
```

**Windows:** Tidak perlu setting apa-apa.

#### Langkah 5: Akses Aplikasi

Buka browser: **http://localhost/exam6lock**

Login admin: http://localhost/exam6lock/admin/login.php → `admin` / `admin123`

---

### 🔧 Setup Database via Command Line (Alternatif)

Buat yang lebih suka terminal:

#### Via Podman (langsung ke container MySQL — paling cepat)
```bash
# Import database langsung ke container (beberapa detik, tidak lewat browser)
podman exec -i exam6lock-db mysql -uroot -prootpass ujian_online < backup_db/ujian_online.sql
```

> ⚠️ Kalau error `Warning` di baris pertama SQL, buka file `backup_db/ujian_online.sql` dan hapus baris pertama yang diawali `mysqldump: [Warning]`.

#### Via XAMPP / MySQL langsung

**Langkah 1: Masuk ke MySQL**
```bash
# XAMPP (Windows)
"C:\xampp\mysql\bin\mysql.exe" -u root

# Linux/Mac
mysql -u root -p
```

**Langkah 2: Buat database dan import**
```sql
-- Jalankan perintah ini satu per satu di MySQL:
CREATE DATABASE IF NOT EXISTS ujian_online CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ujian_online;
SOURCE lokasi_folder_exam6/backup_db/ujian_online.sql;
EXIT;
```

**Atau satu baris:**
```bash
# XAMPP (Windows):
"C:\xampp\mysql\bin\mysql.exe" -u root ujian_online < "C:\xampp\htdocs\exam6lock\backup_db\ujian_online.sql"

# Linux/Mac:
mysql -u root -p ujian_online < /var/www/html/exam6lock/backup_db/ujian_online.sql
```

---

### ✅ Verifikasi Instalasi

Setelah semua langkah di atas:

1. **Buka** http://localhost:8024 (Podman) atau http://localhost/exam6lock (Manual)
2. **Login admin**: http://localhost:8024/admin/login.php — `admin` / `admin123`
3. Di dashboard, klik **"Manajemen Ujian"** — seharusnya sudah ada ujian contoh
4. Klik **"Soal"** pada salah satu ujian — seharusnya sudah ada soal
5. Buka tab baru, buka http://localhost:8024 — lihat daftar ujian yang tersedia
6. Klik **"Mulai Ujian"** pada salah satu ujian, isi identitas contoh, dan coba kerjakan

> ❓ **Udah login tapi error?** Cek kembali langkah import database. Pastikan file `backup_db/ujian_online.sql` sudah diimport dengan benar.

---

## Cara Penggunaan

### Admin

#### 1. Login Admin
- Buka `/admin/login.php`
- Masukkan username: `admin`, password: `admin123`
- ⚠️ Segera ganti password setelah login pertama

#### 2. Pengaturan Profil Sekolah
- Dashboard → **Profil Sekolah**
- Isi nama sekolah
- Upload logo sekolah (format: PNG/JPG, max 2MB)
- Atur warna primer dan sekunder (untuk tema aplikasi)
- Pilih apakah riwayat nilai ditampilkan untuk siswa

#### 3. Manajemen Ujian
- Dashboard → **Manajemen Ujian** → **Tambah Ujian**
- Isi form:
  - **Judul Ujian**: Nama ujian yang akan ditampilkan
  - **Deskripsi**: Keterangan tambahan (opsional)
  - **Durasi**: Waktu pengerjaan dalam menit
  - **Status**: Aktif/Nonaktif
  - **Acak Soal**: Ya/Tidak (soal akan diacak per siswa)
  - **Tampilkan Review**: Izinkan siswa melihat review setelah submit
  
- **Pengaturan Keamanan** (di form ujian):
  - **Kode Ujian**: Kode rahasia yang harus dimasukkan siswa
  - **Batasan IP**: Batasi akses dari IP tertentu (pisahkan dengan koma)
  - **Browser Lock**: Deteksi tab switching (auto-submit setelah X pelanggaran)
  - **Device Fingerprint**: Deteksi pergantian device/browser

#### 4. Mengelola Soal
- Di daftar ujian, klik tombol **"Soal"** pada ujian tertentu
- **Tambah Soal**:
  - Isi teks soal (mendukung HTML)
  - Upload gambar soal (opsional)
  - Tambah pilihan jawaban (A, B, C, D, E)
  - Pilih kunci jawaban benar
  - Isi poin soal
  - **Kategori soal**: Pilih dari dropdown (Mudah/Sedang/Sulit) atau kosongkan untuk auto-kategorisasi
  - Timer per soal (opsional, dalam detik)
  
- **Edit/Hapus Soal**: Klik tombol edit/hapus pada daftar soal

- **Import Soal Massal**:
  1. Klik menu **"Import Massal"**
  2. Download template CSV terlebih dahulu
  3. Isi template dengan data soal (kolom kategori: Mudah/Sedang/Sulit)
  4. Upload file CSV
  5. Sistem akan validasi otomatis

- **Ekspor Soal ke PDF**:
  - Klik menu **"Ekspor PDF"**
  - Pilih ujian yang ingin diekspor
  - Download PDF lengkap dengan kunci jawaban

#### 5. Monitor Ujian Real-time
- Dashboard → **Monitor Ujian**
- Pilih ujian yang sedang berlangsung
- Lihat daftar siswa yang sedang mengerjakan:
  - NIS, Nama, Kelas
  - Progress (soal ke berapa dari total)
  - Waktu mulai dan status
  - IP Address dan device info
  
- **Aksi yang tersedia**:
  - **Reset Ujian Siswa**: Hapus hasil ujian agar siswa bisa mengerjakan ulang
  - **Hapus dari Progress**: Hapus siswa dari daftar yang sedang ujian
  - **Lihat Pelanggaran**: Cek tab switching/device change

#### 6. Rekap Nilai & Izin Remedi
- Dashboard → **Rekap Nilai**
- Pilih ujian yang ingin direkap
- Lihat tabel nilai:
  - NIS, Nama, Kelas
  - Skor total dan persentase
  - Waktu submit
  - Status kelulusan
  
- **Izin Remedi**: Klik tombol "Beri Izin" pada siswa yang perlu remedial — siswa bisa ujian ulang
- Lihat daftar siswa yang sudah diberi izin remedi (ditandai di tabel)
  
- **Ekspor ke Excel**:
  - Klik tombol "Ekspor Excel"
  - File Excel akan diunduh dengan format rapi
  - Berisi semua detail jawaban per siswa

#### 7. Izin Remedi Siswa
- Dashboard → **Izin Remedi** (atau langsung dari Rekap Nilai)
- Atur izin remedi per siswa per ujian
- siswa yang sudah diberi izin bisa **mengerjakan ulang** ujian
- Izin bisa dicabut kembali oleh admin

#### 7. Analytics Dashboard (NEW)
- Dashboard → **Analytics Dashboard**
- Pilih ujian yang ingin dianalisis
- Atur KKM (Kriteria Ketuntasan Minimal) - default 75
- **Fitur Analytics**:
  - **Summary Statistics**: Total peserta, rata-rata skor, completion rate
  - **Distribusi Grade Dinamis** (berdasarkan KKM):
    - Grade A: Score ≥ (KKM + 17) → Sangat Baik
    - Grade B: Score ≥ (KKM + 9) → Baik
    - Grade C: Score ≥ KKM → Cukup (Tuntas)
    - Grade D: Score < KKM → Perlu Bimbingan (Belum Tuntas)
  - **Analisis Butir Soal** (Top 20 terburuk):
    - Question ID, Category (Mudah/Sedang/Sulit)
    - Success rate, correct count, average poin
    - Kategori otomatis berdasarkan tingkat keberhasilan
  - **Top Scorers**: 10 siswa dengan nilai tertinggi
  - **Students Needing Remedial**: Siswa dengan skor < KKM
  - **Visualisasi Grafik** (Chart.js):
    - Grade Distribution Chart
    - Score Distribution Chart
    - Question Analysis Chart (horizontal bar)
  - **Export ke Excel**: Download laporan lengkap (.xls) kompatibel LibreOffice/WPS Office

---

### Siswa

#### 1. Login / Dashboard
- Buka halaman utama aplikasi (http://localhost:8024)
- Klik **"Login Siswa"** di hero section, atau klik **"Login untuk Mengerjakan"** pada kartu ujian
- Masukkan **NIS** dan **Password**
- Login berhasil → masuk ke **Dashboard Siswa**:
  - 👋 Greeting dengan nama & kelas
  - **4 Stat Cards**: ujian tersedia, sudah dikerjakan, rata-rata nilai, sisa ujian
  - **Pengumuman** terbaru (3 terakhir, filter by kelas)
  - **Ujian Tersedia** (4 teratas) — klik "Mulai" untuk langsung ke ujian
  - **Riwayat Nilai** (5 terakhir) — klik "Detail" untuk lihat pembahasan jawaban

#### 2. Navigasi
- **Top Navbar** selalu tersedia:
  - **Beranda**: Kembali ke Dashboard
  - **Ujian**: Lihat semua ujian tersedia (landing page)
  - **Nilai**: Riwayat nilai semua ujian
  - **Pengumuman**: Semua pengumuman
  - **Avatar (pojok kanan)**: Profil, Ganti Password, Logout
- Di HP, avatar dan hamburger menu selalu terlihat — dropdown user bisa diakses langsung

#### 3. Saat Ujian
- Klik **"Mulai Ujian"** dari dashboard atau kartu ujian di landing page
- Jika sudah pernah submit & **tidak ada izin remedi** → muncul halaman blokir dengan tombol "Kembali ke Dashboard"
- Jika sudah pernah submit & **ada izin remedi dari admin** → lewati blokir, lanjut ujian ulang
- Jika ujian punya **Kode Rahasia** → masukkan kode (harus diisi setiap kali ganti ujian)
- Identitas terisi otomatis dari session (NIS, Nama, Kelas readonly) → klik **"Mulai Ujian"**

#### 4. Mengerjakan Soal
- Soal ditampilkan 1 per 1 halaman
- Navigasi menggunakan:
  - **Previous/Next button** di bawah soal
  - **Grid nomor** (klik untuk lompat ke soal tertentu)
- **Indicator warna**:
  - 🔵 **Biru**: Soal yang sedang dikerjakan
  - 🟢 **Hijau**: Soal sudah dijawab
  - ⚪ **Abu-abu**: Soal belum dijawab
- **Timer** akan berjalan (per ujian atau per soal)
- Jawaban **auto-save** setiap 30 detik
- Jika halaman refresh/crash, jawaban dimuat otomatis

#### 5. Submit & Hasil
- Klik **"Kirim Jawaban"** setelah selesai
- Nilai muncul langsung setelah submit
- Jika diaktifkan, klik **"Lihat Review"** untuk lihat pembahasan:
  - Score summary (total skor, benar/total, persentase)
  - Filter: semua / benar saja / salah saja
  - Tiap soal: opsi jawaban, tanda jawaban benar ✅ vs jawaban siswa ❌

#### 6. Riwayat Nilai
- Dari navbar klik **Nilai** atau dari dashboard lihat **Riwayat Nilai Terakhir**
- Klik **"Detail"** untuk lihat pembahasan jawaban lengkap

---

## Pengaturan Keamanan Ujian

Saat membuat/mengedit ujian di admin, tersedia pengaturan keamanan:

| Fitur | Deskripsi | Rekomendasi |
|-------|-----------|-------------|
| **Kode Ujian** | Kode rahasia yang harus dimasukkan siswa untuk mengakses ujian | Aktifkan untuk ujian penting |
| **Batasan IP** | Batasi akses ujian hanya dari IP tertentu (pisahkan dengan koma) | Gunakan untuk ujian di lab sekolah |
| **Browser Lock** | Deteksi jika siswa switch tab/copy-paste. Akan melakukan auto-submit setelah X pelanggaran | Aktifkan untuk ujian bersifat formal |
| **Device Fingerprint** | Deteksi jika siswa ganti device/browser di tengah ujian | Aktifkan untuk mencegah kecurangan |

**Contoh pengaturan:**
```
Kode Ujian: UJIAN2024
Batasan IP: 192.168.1.10, 192.168.1.11, 192.168.1.12
Browser Lock: 3 pelanggaran (auto-submit)
Device Fingerprint: Aktif
```

---

## Alur Ujian

1. **Halaman Utama**: Siswa melihat hero + daftar ujian tersedia
2. **Login**: Klik "Login Siswa" atau "Login untuk Mengerjakan" → **Dashboard Siswa**
3. **Dashboard**: Lihat pengumuman, statistik, pilih ujian → klik "Mulai"
4. **Pre-check**: Jika sudah pernah submit, cek izin remedi — blokir atau lanjut
5. **Kode Ujian** (jika ada): Masukkan kode rahasia (wajib per ujian, tidak persist)
6. **Identitas**: Terisi otomatis (readonly) dari session → klik "Mulai Ujian"
7. **Soal**: Jawab 1 soal per halaman dengan navigasi:
   - Previous/Next button
   - Grid nomor soal untuk lompat ke soal tertentu
   - Indicator warna: abu (belum), hijau (dijawab), biru (aktif)
8. **Submit**: Klik "Kirim Jawaban" jika sudah selesai
9. **Hasil**: Lihat skor langsung setelah submit
10. **Review** (opsional): Lihat detail jawaban, filter benar/salah, opsi jawaban

---

## Akun Default

| Role | Username/NIS | Password | Keterangan |
|------|-------------|----------|------------|
| Admin | `admin` | `admin123` | Segera ganti setelah login pertama |
| Siswa | (lihat data siswa di database) | `siswa123` (default) | Atur di Manajemen Siswa → Import |

> ⚠️ **Penting**: Untuk keamanan, segera ubah password default admin.

---

## Teknologi

- **Frontend**: 
  - Bootstrap 5 (CSS Framework)
  - Bootstrap Icons (Icon library)
  - Vanilla JavaScript (ES6+)
  - Google Fonts (Inter)
  - Chart.js 4.4.1 (Data visualization)
  
- **Backend**: 
  - PHP 8.2 Native (tanpa framework)
  - MySQL 8.0 (database)
  - JSON untuk penyimpanan jawaban
  
- **Container**: 
  - Podman
  - PHP 8.2 Apache image
  - MySQL 8.0 image
  - phpMyAdmin latest

- **Tools**:
  - Composer (opsional, untuk pengembangan)
  - Git untuk version control

---

## Troubleshooting

### 1. Error: "Koneksi database gagal"
- **Penyebab paling umum**: Database belum dibuat atau file SQL belum diimport
- Cek konfigurasi di `config/database.php`:
  ```php
  // XAMPP: password kosong, host localhost
  // Podman: host = db, password = rootpass
  ```
- Untuk Podman: pastikan container `exam6lock-db` sudah running (`podman ps`)
- Coba akses phpMyAdmin untuk pastikan database `ujian_online` ada dan berisi tabel

### 2. Error "Table 'ujian_online.ujian' doesn't exist" atau tabel tidak ditemukan
- **Penyebab**: File `backup_db/ujian_online.sql` belum diimport
- Solusi: Ikuti langkah import database di atas (bagian Setup Database)
- Untuk Podman via phpMyAdmin: http://localhost:8025 → login → pilih `ujian_online` → Import
- Untuk XAMPP via phpMyAdmin: http://localhost/phpmyadmin → Import

### 3. Gambar soal tidak muncul
- Pastikan folder `uploads/` memiliki permission 777 (Linux/Mac)
- Cek apakah file gambar ada di folder `uploads/`

### 4. Auto-save tidak berfungsi
- Pastikan JavaScript tidak diblokir browser
- Cek console browser (F12) untuk error

### 5. Siswa tidak bisa submit atau diblokir saat akses ujian
- **Sudah pernah submit & tidak ada izin remedi** → halaman blokir muncul, klik "Kembali ke Dashboard"
- Minta admin untuk memberikan **izin remedi** di menu **Izin Remedi** atau **Rekap Nilai**
- Cek apakah ada pelanggaran browser lock (terlalu sering tab switch)

### 6. Container tidak bisa start
```bash
# Cek logs
podman logs exam6lock-app
podman logs exam6lock-db

# Restart containers
podman compose down
podman compose up -d
```

### 7. Database error setelah update
```bash
# Jalankan migration terbaru (jika ada file baru di migrations/)
# Lewat phpMyAdmin: buka database ujian_online → SQL → paste isi file migration
# Atau command line:
mysql -u root -p ujian_online < migrations/08_increase_max_violations.sql
```

---

## Lisensi

MIT License

Copyright (c) 2026 Exam6 - Sistem Ujian Online

Dibenarkan untuk menggunakan, memodifikasi, dan mendistribusikan aplikasi ini dengan atau tanpa modifikasi untuk keperluan komersial maupun non-komersial.

---

## Kontribusi

Kontribusi selalu diterima! Silakan:
1. Fork repository ini
2. Buat branch fitur (`git checkout -b fitur/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin fitur/AmazingFeature`)
5. Buat Pull Request

---

## Kontak & Support

- **Repository**: https://github.com/natedekaka/exam6lock
- **Issues**: https://github.com/natedekaka/exam6lock/issues
- **Email**: natedekaka@gmail.com

---

**Dibuat dengan ❤️ untuk dunia pendidikan Indonesia spesial sman6cimahi**
