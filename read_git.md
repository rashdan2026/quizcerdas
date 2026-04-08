#  QuizCerdas - Sistem Absensi Mahasiswa Berbasis QR Code & OTP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4.svg)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.4-EF4223.svg)](https://codeigniter.com/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://www.mysql.com/)

---

## 📖 Deskripsi Aplikasi

**QuizCerdas** adalah sistem absensi mahasiswa digital berbasis web yang dirancang untuk mempermudah proses pencatatan kehadiran dalam perkuliahan. Aplikasi ini menggunakan teknologi **QR Code** untuk validasi kehadiran dan **OTP (One-Time Password)** untuk keamanan autentikasi mahasiswa.

### 🎯 Tujuan Utama
- Menggantikan absensi manual dengan sistem digital yang lebih akurat
- Mencegah kecurangan absensi melalui validasi QR Code dan OTP
- Memberikan laporan kehadiran real-time untuk dosen
- Memudahkan mahasiswa melakukan absensi melalui perangkat mobile
- Menyediakan akses materi kuliah (PDF) hanya untuk mahasiswa yang hadir

---

## ✨ Fitur Utama

### 👨‍ **Fitur Mahasiswa**
| Fitur | Deskripsi |
|-------|-----------|
| 🔐 **Login Dual Mode** | Login menggunakan Email atau NPM (Nomor Pokok Mahasiswa) |
| 🔑 **OTP Authentication** | Kode OTP dikirim via email setiap 5 kali login |
| 📱 **QR Code Scanning** | Scan QR Code untuk absensi (auto-refresh setiap 30 detik) |
| 📍 **Geolokasi** | Validasi lokasi GPS saat absensi (wajib aktif) |
| 📊 **Dashboard Absensi** | Riwayat kehadiran per mata kuliah |
| 📄 **PDF Viewer** | Akses materi kuliah (PDF) hanya setelah absensi valid |
| 🔒 **Change Password** | Ubah password saat login pertama kali |

### 👨‍ **Fitur Dosen**
| Fitur | Deskripsi |
|-------|-----------|
| 🔐 **Login Email** | Login menggunakan email dan password |
| 📊 **Rekap Absensi** | Lihat jumlah kehadiran per pertemuan |
| 👥 **Detail Kehadiran** | Lihat daftar mahasiswa yang hadir dengan lokasi GPS |
| ✏️ **Manual Attendance** | Tambahkan absensi manual untuk mahasiswa |
| 📋 **Export Data** | Lihat data kehadiran dalam format tabel |

### 🛡️ **Keamanan**
| Fitur | Deskripsi |
|-------|-----------|
| 🎯 **Captcha** | Captcha image saat login untuk mencegah bot |
| 🔑 **OTP Email** | One-Time Password dikirim via Gmail/Turbo-SMTP |
| 📍 **GPS Validation** | Validasi koordinat lokasi saat absensi |
| ⏰ **Token Expiry** | QR Code token expired otomatis (default 30 detik) |
| 🔒 **Password Hash** | Password di-hash menggunakan bcrypt |
| 🎲 **Dynamic QR** | QR Code di-generate random dan berubah tiap sesi |

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                      CLIENT LAYER                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │   Web Browser │  │  Mobile App  │  │   QR Scanner    │  │
│  │  (Desktop)    │  │  (Responsive)│  │   (Camera)      │  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              CodeIgniter 4 Framework                  │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │  │
│  │  │ Routes   │ │ Controllers │ │  Models  │ │ Filters │ │  │
│  │  └────────── └──────────┘ └──────────┘ └─────────┘ │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │  │
│  │  │  Views   │ │ Helpers  │ │ Libraries │ │ Config  │ │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └─────────┘ │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     DATA LAYER                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │   MySQL DB   │  │  Migration   │  │     Seeder       │  │
│  │ (quizcerdas) │  │  (Versioned) │  │  (Test Data)     │  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   EXTERNAL SERVICES                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │  Gmail API   │  │ Turbo-SMTP   │  │  Google Maps    │  │
│  │  (OTP Mail)  │  │ (Fallback)   │  │  (GPS Verify)    │  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Struktur Project

```
quizcerdas/
│
├── 📄 index.php                      # Entry point aplikasi
├── 📄 composer.json                  # Dependency management
├──  composer.lock                  # Locked dependency versions
├── 📄 .env.example                   # Template konfigurasi lingkungan
│
├── 📁 app/                           # APLIKASI UTAMA
│   ├── 📁 Config/                    # Konfigurasi framework
│   │   ├── Routes.php                # Definisi routing
│   │   ├── Database.php              # Konfigurasi database
│   │   ├── Email.php                 # Konfigurasi email
│   │   ├── App.php                   # Konfigurasi aplikasi
│   │   └── ...                       # Konfigurasi lainnya
│   │
│   ├── 📁 Controllers/               # LOGIKA KONTROLER
│   │   ├── BaseController.php        # Base controller
│   │   ├── Auth.php                  # Autentikasi (login, OTP, logout)
│   │   ├── 📁 Student/               # Kontroller mahasiswa
│   │   │   ├── Dashboard.php         # Dashboard mahasiswa
│   │   │   ├── Scan.php              # Scan QR & submit absensi
│   │   │   ├── PdfViewer.php         # View materi PDF
│   │   │   └── PdfFile.php           # Serve file PDF
│   │   └── 📁 Lecturer/              # Kontroller dosen
│   │       ├── Report.php            # Rekap & detail absensi
│   │       ├── MeetingController.php # Kelola pertemuan
│   │       └── SubjectController.php # Kelola mata kuliah
│   │
│   ├── 📁 Models/                    # MODEL DATABASE
│   │   ├── StudentModel.php          # Model mahasiswa (PK: email)
│   │   ├── LecturerModel.php         # Model dosen
│   │   ├── SubjectModel.php          # Model mata kuliah
│   │   ├── MeetingModel.php          # Model pertemuan
│   │   ├── AttendanceModel.php       # Model absensi
│   │   ├── OtpCodeModel.php          # Model kode OTP
│   │   └── PdfFileModel.php          # Model file PDF
│   │
│   ├── 📁 Views/                     # TAMPILAN (HTML/Bootstrap)
│   │   ├── 📁 auth/                  # Halaman autentikasi
│   │   │   ├── login.php             # Form login
│   │   │   ├── otp.php               # Input kode OTP
│   │   │   └── change_password.php   # Ubah password
│   │   ├── 📁 student/               # Tampilan mahasiswa
│   │   │   ├── dashboard.php         # Dashboard absensi
│   │   │   ├── scan.php              # Scan QR Code
│   │   │   └── detail.php            # Detail pertemuan + PDF viewer
│   │   ├── 📁 lecturer/              # Tampilan dosen
│   │   │   ├── report.php            # Rekap absensi
│   │   │   ├── subjects.php          # Daftar mata kuliah
│   │   │   └── meetings/             # Kelola pertemuan
│   │   └── 📁 layouts/               # Layout template
│   │       └── app.php               # Master layout
│   │
│   ├── 📁 Database/                  # DATABASE
│   │   ├── 📁 Migrations/            # Versioned schema changes
│   │   │   ├── 2026-04-05-000001_CreateAttendanceSystem.php
│   │   │   ├── 2026-04-05-000002_AlterStudentsForDb2Sync.php
│   │   │   ├── 2026-04-05-000003_AddLinkPdfToMeetings.php
│   │   │   ├── 2026-04-05-000004_CreatePdfFilesTable.php
│   │   │   ├── 2026-04-05-000005_AlterMeetingsForPdfFileRef.php
│   │   │   ├── 2026-04-08-000001_AddLoginCountToStudents.php
│   │   │   └── 2026-04-08-000002_ChangeStudentsPrimaryKeyToEmail.php
│   │   └── 📁 Seeds/                 # Seed data
│   │       └── AppSeeder.php         # Data awal (dosen, mahasiswa, dll)
│   │
│   ├── 📁 Libraries/                 # LIBRARY CUSTOM
│   │   └── CaptchaLibrary.php        # Generator captcha
│   │
│   ├── 📁 Helpers/                   # HELPER FUNCTIONS
│   │   └── auth_helper.php           # Helper autentikasi
│   │
│   ├── 📁 Filters/                   # MIDDLEWARE
│   │   ├── AuthFilter.php            # Filter autentikasi
│   │   └── RoleFilter.php            # Filter role (student/lecturer)
│   │
│   ├── 📁 ThirdParty/                # THIRD-PARTY LIBRARIES
│   │   └── Escaper/                  # HTML Escaper library
│   │
│   └──  Language/                  # BAHASA
│       └── en/                       # English language files
│
├── 📁 public/                        # WEB ROOT (AKSES PUBLIK)
│   ├── 📄 index.php                  # Front controller
│   ├── 📄 .htaccess                  # Apache rewrite rules
│   ├── 📄 favicon.ico                # Icon website
│   └──  robots.txt                 # Robots crawling rules
│
├──  system/                        # CODEIGNITER FRAMEWORK CORE
│   ├── 📁 Commands/                  # CLI commands
│   ├── 📁 Config/                    # Framework config
│   ├── 📁 Database/                  # Database layer
│   ├── 📁 HTTP/                      # HTTP handling
│   ├── 📁 ThirdParty/                # Third-party libs (Kint, etc)
│   └── ...                           # Core framework files
│
├──  vendor/                        # COMPOSER DEPENDENCIES
│   └── (Generated by composer)       # Autoload & packages
│
├──  writable/                      # FOLDER YANG BISA DITULIS
│   ├── 📁 logs/                      # Log aplikasi
│   ├── 📁 session/                   # Session data
│   ├── 📁 cache/                     # Cache data
│   ├── 📁 uploads/                   # File upload (PDF materi)
│   │   └── 📁 pdf/                   # PDF files
│   └──  debugbar/                  # Debug bar data
│
├── 📁 .qwen/                         # Qwen Code configuration
│   └── settings.json                 # IDE settings
│
├── 📄 deploy.sh                      # Deployment script (Linux/Mac)
├──  deploy.ps1                     # Deployment script (Windows)
├──  README.md                      # Dokumentasi ini
├── 📄 LICENSE                        # License file
├── 📄 UPLOAD_INSTRUCTIONS.md         # Panduan upload file PDF
├── 📄 .gitignore                     # Git ignore rules
└── 📄 read_git.md                    # Dokumentasi teknis (file ini)
```

---

## 🗄️ Struktur Database

### **Tabel: `students`** (Mahasiswa)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `email` | VARCHAR(255) | **PRIMARY KEY** | Email mahasiswa (unique identifier) |
| `npm` | VARCHAR(9) | UNIQUE | Nomor Pokok Mahasiswa |
| `nama` | VARCHAR(100) | | Nama lengkap mahasiswa |
| `kelas` | CHAR(1) | | Kelas (A/B/C) |
| `no_whatsapp` | VARCHAR(15) | | Nomor WhatsApp |
| `password` | VARCHAR(255) | | Password (bcrypt hash) |
| `passwd` | VARCHAR(255) | | Password alternatif (legacy) |
| `last_login` | DATETIME | | Waktu login terakhir |
| `login_count` | INT | | Counter login (OTP trigger setiap 5x) |
| `created_at` | DATETIME | | Waktu pembuatan record |
| `updated_at` | DATETIME | | Waktu update terakhir |

### **Tabel: `lecturers`** (Dosen)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID dosen (auto increment) |
| `nama` | VARCHAR(100) | | Nama lengkap dosen |
| `email` | VARCHAR(100) | UNIQUE | Email dosen |
| `password` | VARCHAR(255) | | Password (bcrypt hash) |
| `last_login` | DATETIME | | Waktu login terakhir |
| `created_at` | DATETIME | | Waktu pembuatan record |
| `updated_at` | DATETIME | | Waktu update terakhir |

### **Tabel: `subjects`** (Mata Kuliah)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID mata kuliah |
| `kode_mk` | VARCHAR(10) | UNIQUE | Kode mata kuliah |
| `nama_mk` | VARCHAR(100) | | Nama mata kuliah |
| `is_active` | BOOLEAN | | Status aktif/tidak |
| `dosen_id` | INT UNSIGNED | FOREIGN KEY → lecturers.id | Dosen pengampu |
| `created_at` | DATETIME | | Waktu pembuatan |
| `updated_at` | DATETIME | | Waktu update |

### **Tabel: `meetings`** (Pertemuan)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID pertemuan |
| `subject_id` | INT UNSIGNED | FOREIGN KEY → subjects.id | Mata kuliah |
| `pertemuan_ke` | INT UNSIGNED | UNIQUE(subject_id, pertemuan_ke) | Nomor pertemuan |
| `judul` | VARCHAR(255) | | Judul pertemuan |
| `deskripsi` | TEXT | | Deskripsi materi |
| `link_video` | VARCHAR(255) | | Link video pembelajaran |
| `token_qr` | VARCHAR(100) | | QR Code token (random) |
| `expired_at` | DATETIME | | Waktu expired token |
| `pdf_file_id` | INT UNSIGNED | FOREIGN KEY → pdf_files.id | ID file PDF materi |
| `created_at` | DATETIME | | Waktu pembuatan |
| `updated_at` | DATETIME | | Waktu update |

### **Tabel: `attendance`** (Absensi)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID absensi |
| `meeting_id` | INT UNSIGNED | FOREIGN KEY → meetings.id | Pertemuan |
| `email` | VARCHAR(255) | FOREIGN KEY → students.email, UNIQUE(meeting_id, email) | Email mahasiswa |
| `latitude` | DECIMAL(10,8) | | Koordinat latitude |
| `longitude` | DECIMAL(11,8) | | Koordinat longitude |
| `waktu_absen` | TIMESTAMP | | Waktu absensi |

### **Tabel: `otp_codes`** (Kode OTP)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID OTP |
| `email` | VARCHAR(100) | FOREIGN KEY → students.email, INDEX | Email mahasiswa |
| `otp_code` | VARCHAR(6) | | Kode OTP (6 digit) |
| `expired_at` | DATETIME | | Waktu expired (5 menit) |
| `is_used` | BOOLEAN | | Status sudah digunakan |
| `created_at` | DATETIME | | Waktu pembuatan |
| `updated_at` | DATETIME | | Waktu update |

### **Tabel: `pdf_files`** (File PDF Materi)
| Kolom | Tipe | Key | Deskripsi |
|-------|------|-----|-----------|
| `id` | INT UNSIGNED | **PRIMARY KEY** | ID file |
| `file_name` | VARCHAR(255) | | Nama file di server |
| `original_name` | VARCHAR(255) | | Nama file original |
| `file_size` | BIGINT | | Ukuran file (bytes) |
| `mime_type` | VARCHAR(100) | | MIME type |
| `created_at` | DATETIME | | Waktu upload |

---

## 🔌 API & Routing

### **Routes Mahasiswa**
| Method | URL | Controller | Deskripsi |
|--------|-----|------------|-----------|
| GET | `/student/dashboard` | Student\Dashboard::index | Dashboard absensi |
| GET | `/student/scan` | Student\Scan::index | Halaman scan QR |
| POST | `/student/scan/submit` | Student\Scan::submit | Submit absensi |
| GET | `/student/detail/{id}` | Student\Scan::detail | Detail pertemuan |
| GET | `/student/pdf/{id}` | Student\PdfViewer::index | View PDF materi |
| GET | `/student/pdf-file/{id}` | Student\PdfFile::view | Serve PDF file |

### **Routes Dosen**
| Method | URL | Controller | Deskripsi |
|--------|-----|------------|-----------|
| GET | `/lecturer/report` | Lecturer\Report::index | Rekap absensi |
| GET | `/lecturer/report/detail/{id}` | Lecturer\Report::detail | Detail kehadiran |
| POST | `/lecturer/report/add` | Lecturer\Report::addManual | Tambah absensi manual |
| GET | `/lecturer/subjects` | Lecturer\SubjectController::index | Daftar mata kuliah |
| GET | `/lecturer/meetings` | Lecturer\MeetingController::index | Daftar pertemuan |
| POST | `/lecturer/meetings/create` | Lecturer\MeetingController::create | Buat pertemuan baru |

### **Routes Auth**
| Method | URL | Controller | Deskripsi |
|--------|-----|------------|-----------|
| GET | `/auth` | Auth::index | Halaman login |
| POST | `/auth/login` | Auth::login | Proses login |
| GET | `/auth/otp` | Auth::otp | Halaman input OTP |
| POST | `/auth/otp` | Auth::otp | Verifikasi OTP |
| GET | `/auth/change-password` | Auth::changePassword | Halaman ubah password |
| POST | `/auth/change-password` | Auth::changePassword | Proses ubah password |
| GET | `/auth/logout` | Auth::logout | Logout |

---

## 🔧 Teknologi & Dependencies

### **Backend**
- **PHP 8.1+** - Language runtime
- **CodeIgniter 4.4+** - PHP Framework
- **MySQL 5.7+** - Database server
- **Composer** - Dependency manager

### **Frontend**
- **Bootstrap 5** - CSS framework
- **Bootstrap Icons** - Icon library
- **jQuery 3.x** - JavaScript library
- **HTML5 QR Scanner** - QR Code scanner library
- **PDF.js** - PDF viewer (Mozilla)

### **External Services**
- **Gmail API (webriau.com)** - Primary OTP email service
- **Turbo-SMTP API** - Fallback OTP email service
- **Google Maps** - GPS location verification

### **Development Tools**
- **Git** - Version control
- **GitHub** - Repository hosting
- **Plesk** - Web hosting control panel
- **SQLyog** - MySQL GUI client

---

## 🚀 Installation & Setup

### **Requirements**
- PHP 8.1 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Composer
- Apache/Nginx web server
- Extension PHP: `mbstring`, `intl`, `json`, `mysqlnd`, `curl`

### **Langkah Instalasi**

#### **1. Clone Repository**
```bash
git clone https://github.com/rashdan2026/quizcerdas.git
cd quizcerdas
```

#### **2. Install Dependencies**
```bash
composer install
```

#### **3. Konfigurasi Environment**
```bash
cp .env.example .env
# Edit file .env sesuai konfigurasi lokal
```

#### **4. Setup Database**
```bash
# Buat database baru
mysql -u root -p -e "CREATE DATABASE quizcerdas;"

# Jalankan migration
php spark migrate

# (Opsional) Seed data awal
php spark db:seed AppSeeder
```

#### **5. Konfigurasi Web Server**

**Apache (.htaccess sudah ada):**
```apache
DocumentRoot /path/to/quizcerdas/public
<Directory /path/to/quizcerdas/public>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/quizcerdas/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

#### **6. Set Permissions**
```bash
# Linux/Mac
chmod -R 777 writable/
chmod -R 777 vendor/

# Windows
# Set folder 'writable' dan 'vendor' writable via Properties > Security
```

#### **7. Akses Aplikasi**
```
http://localhost/quizcerdas/public/
```

---

## 📧 Konfigurasi Email OTP

### **Option 1: Gmail API (Primary)**
Edit file `.env`:
```env
otp.mailApiUrl = 'https://gmail.webriau.com/servera.php'
otp.mailApiKey = 'your_api_key_here'
```

### **Option 2: Turbo-SMTP (Fallback)**
Edit file `.env`:
```env
turboSmtp.consumerKey = 'your_consumer_key'
turboSmtp.consumerSecret = 'your_consumer_secret'
```

Aplikasi akan otomatis menggunakan Turbo-SMTP jika Gmail API gagal.

---

## 🔐 Default Credentials (Development)

### **Dosen**
| Email | Password |
|-------|----------|
| dosen@kampus.ac.id | dosen123 |

### **Mahasiswa**
| Email | NPM | Password |
|-------|-----|----------|
| mahasiswa1@kampus.ac.id | 230000001 | student123 |
| mahasiswa2@kampus.ac.id | 230000002 | student123 |

⚠️ **PENTING**: Ubah password default setelah login pertama kali!

---

## 🔄 Workflow Aplikasi

### **Alur Absensi Mahasiswa**
```
1. Mahasiswa login (email/NPM + password + captcha)
   ↓
2. Jika login ke-5, sistem kirim OTP via email
   ↓
3. Mahasiswa input kode OTP (6 digit, valid 5 menit)
   ↓
4. Masuk ke dashboard → Pilih pertemuan
   ↓
5. Scan QR Code yang ditampilkan dosen
   ↓
6. Sistem validasi:
   - Token QR cocok dengan server
   - Token belum expired (30 detik grace period)
   - Belum pernah absen di pertemuan ini
   ↓
7. Ambil lokasi GPS (wajib)
   ↓
8. Submit absensi → Tersimpan di database
   ↓
9. Akses materi PDF (jika tersedia)
```

### **Alur Dosen**
```
1. Dosen login (email + password)
   ↓
2. Buat mata kuliah & pertemuan
   ↓
3. Generate QR Code (auto-refresh setiap 30 detik)
   ↓
4. Tampilkan QR Code di kelas
   ↓
5. Mahasiswa scan & absen
   ↓
6. Dosen lihat rekap absensi real-time
   ↓
7. Tambah absensi manual (jika diperlukan)
```

---

## 🛠️ Development Guide

### **Membuat Migration Baru**
```bash
php spark make:migration AddNewFeature --all
```

### **Menjalankan Migration**
```bash
php spark migrate
```

### **Rollback Migration**
```bash
php spark migrate:rollback
```

### **Membuat Seeder**
```bash
php spark make:seeder DataSeeder
```

### **Menjalankan Seeder**
```bash
php spark db:seed DataSeeder
```

### **Membuat Controller**
```bash
php spark make:controller Admin/UserController
```

### **Membuat Model**
```bash
php spark make:model User
```

### **Debugging**
```bash
# Enable debug bar (di .env)
CI_ENVIRONMENT = development

# Lihat log
tail -f writable/logs/log-*.php
```

---

## 📦 Deployment

### **Deploy ke GitHub**

**Linux/Mac:**
```bash
bash deploy.sh
```

**Windows (PowerShell):**
```powershell
.\deploy.ps1
```

Script akan:
1. ✅ Cek perubahan (modified, new, deleted files)
2. ✅ Skip jika tidak ada perubahan
3. ✅ Commit perubahan
4. ✅ Pull dari GitHub (hindari konflik)
5. ✅ Push ke GitHub
6. ✅ Webhook Plesk otomatis update website

### **Deploy Manual**
```bash
git add .
git commit -m "Your message"
git pull --rebase origin main
git push origin main
```

### **File yang Di-ignore (Tidak Di-upload)**
- `.env` (security credentials)
- `vendor/` (composer dependencies)
- `test_*.php`, `*_test.php` (testing files)
- `debug*.php`, `update_*.php` (temporary files)
- `writable/logs/*`, `writable/session/*`, dll (dynamic data)
- `.qwen/`, `.vscode/`, `.idea/` (editor configs)

---

## 🔒 Security Best Practices

### **Production Checklist**
- [ ] Ubah `CI_ENVIRONMENT` ke `production` di `.env`
- [ ] Ganti `app.encryptionKey` dengan key baru
- [ ] Ubah password default semua user
- [ ] Enable HTTPS/SSL
- [ ] Disable debug bar
- [ ] Set folder permissions yang tepat
- [ ] Backup database secara berkala
- [ ] Update dependencies (`composer update`)
- [ ] Monitor log file secara berkala

### **Password Policy**
- Minimal 8 karakter
- Hash menggunakan bcrypt
- Change password wajib saat login pertama
- Tidak menyimpan password plain text

### **Session Security**
- Session stored di server (folder `writable/session`)
- Session timeout otomatis
- Regenerate session ID setelah login

---

## 🐛 Troubleshooting

### **Error: "Class 'App\Controllers\XXX' not found"**
```bash
composer dump-autoload
```

### **Error: Database connection failed**
- Cek konfigurasi database di `.env`
- Pastikan MySQL service running
- Cek username & password database

### **Error: Permission denied (writable folder)**
```bash
# Linux/Mac
chmod -R 777 writable/

# Windows
# Right-click folder > Properties > Security > Edit > Full Control
```

### **Error: OTP email tidak terkirim**
- Cek koneksi internet
- Verifikasi API key di `.env`
- Cek log di `writable/logs/`
- Fallback ke Turbo-SMTP otomatis aktif

### **Error: QR Code tidak valid**
- Refresh halaman (token expired)
- Cek waktu server (timezone harus sama)
- Verifikasi `app.appTimezone` di `.env`

---

## 📊 Database Migration History

| Migration File | Deskripsi |
|---------------|-----------|
| `2026-04-05-000001` | Create initial schema (lecturers, subjects, meetings, students, attendance, otp_codes) |
| `2026-04-05-000002` | Alter students table (add kelas, passwd, no_whatsapp) |
| `2026-04-05-000003` | Add link_video & pdf_file_path to meetings |
| `2026-04-05-000004` | Create pdf_files table |
| `2026-04-05-000005` | Alter meetings (replace pdf_file_path with pdf_file_id FK) |
| `2026-04-08-000001` | Add login_count column to students |
| `2026-04-08-000002` | **MAJOR**: Change students PK from npm to email, remove npm from attendance & otp_codes |

---

## 📈 Future Development Roadmap

### **Phase 1: Core Features** ✅
- [x] Login system (email/NPM + password)
- [x] OTP authentication
- [x] QR Code scanning
- [x] GPS validation
- [x] Attendance tracking
- [x] PDF material access

### **Phase 2: Enhanced Features** 🔄
- [ ] Export attendance to Excel/PDF
- [ ] Attendance statistics & charts
- [ ] Push notifications (WhatsApp/Telegram)
- [ ] Mobile app (Android/iOS)
- [ ] Biometric authentication (fingerprint/face ID)

### **Phase 3: Advanced Features** 📅
- [ ] Facial recognition for attendance
- [ ] Geo-fencing (radius validation)
- [ ] Offline mode (sync when online)
- [ ] Integration with campus LMS
- [ ] API for third-party integration
- [ ] Multi-language support
- [ ] Dark mode UI

---

## 🤝 Contributing

1. Fork repository ini
2. Buat feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

---

## 📝 License

Distributed under the MIT License. See `LICENSE` file for more information.

---

## 👨‍💻 Developer

**Developed by:** Rashdan  
**GitHub:** [@rashdan2026](https://github.com/rashdan2026)  
**Project:** QuizCerdas - Sistem Absensi Mahasiswa

---

## 📞 Support

Jika mengalami masalah atau butuh bantuan:
- 📧 Email: support@quizcerdas.com
- 🐛 Issue: [GitHub Issues](https://github.com/rashdan2026/quizcerdas/issues)
- 📖 Documentation: [Wiki](https://github.com/rashdan2026/quizcerdas/wiki)

---

## 🙏 Acknowledgments

- [CodeIgniter 4](https://codeigniter.com/) - PHP Framework
- [Bootstrap 5](https://getbootstrap.com/) - CSS Framework
- [PDF.js](https://mozilla.github.io/pdf.js/) - PDF Viewer
- [Bootstrap Icons](https://icons.getbootstrap.com/) - Icon Library
- [HTML5 QR Scanner](https://github.com/nicmcdonald/html5-qrcode) - QR Scanner

---

**© 2026 QuizCerdas. All rights reserved.**
