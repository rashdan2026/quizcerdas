# 📋 INSTRUKSI UPLOAD KE SERVER

## ✅ File/Folder yang PERLU DIUPLOAD:

### **Root Folder (`/absen/`)**
```
✅ .env                      (production config - PASTIKAN PASSWORD DB BENAR)
✅ .env.example
✅ .gitignore
✅ composer.json
✅ composer.lock
✅ index.php                 (auto redirect ke /public/)
✅ LICENSE
✅ README.md
```

### **Folder `vendor/`** ⭐ **PENTING!**
```
✅ vendor/                   (SELURUH FOLDER - hasil composer install)
   ├── autoload.php
   ├── codeigniter4/
   ├── laminas/
   ├── psr/
   └── ... (semua subfolder)
```

### **Folder `app/`**
```
✅ app/                      (SELURUH FOLDER)
   ├── Config/
   ├── Controllers/
   ├── Filters/
   ├── Models/
   ├── Views/
   └── ... (semua subfolder)
```

### **Folder `system/`**
```
✅ system/                   (SELURUH FOLDER - CodeIgniter framework)
```

### **Folder `public/`**
```
✅ public/
   ├── .htaccess
   ├── favicon.ico
   ├── index.php
   ├── robots.txt
   └── check.php            (hapus setelah testing selesai)
```

### **Folder `writable/`**
```
✅ writable/                 (SELURUH FOLDER)
   ├── .htaccess
   ├── logs/                (buat jika belum ada, permission 777)
   ├── session/             (permission 777)
   ├── cache/               (buat jika belum ada, permission 777)
   ├── debugbar/            (permission 777)
   └── uploads/
```

---

## 🚫 File/Folder yang TIDAK PERLU DIUPLOAD:

```
❌ .qwen/                    (IDE settings)
❌ tests/                    (sudah dihapus)
```

---

## 🔧 SETELAH UPLOAD - Jalankan di Server:

### **Via SSH/Plesk Terminal:**

```bash
# 1. Masuk ke folder aplikasi
cd /var/www/vhosts/kursuscerdas.com/httpdocs/absen

# 2. Set permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 777 writable/logs writable/cache writable/session writable/debugbar
chmod 644 .env

# 3. Clear cache (jika ada)
php spark cache:clear

# 4. Test akses website
# https://kursuscerdas.com/absen/public/
```

---

## ⚠️ PENTING - CEK SEBELUM UPLOAD:

### **1. File `.env` - Pastikan Database Config Benar:**
```env
CI_ENVIRONMENT = production

app.baseURL = 'https://kursuscerdas.com/absen/public/'

database.default.hostname = riset.putrariau.com
database.default.database = absencerdas
database.default.username = hendra
database.default.password = [PASTIKAN PASSWORD BENAR]
database.default.DBDriver = MySQLi
database.default.port = 3306
```

### **2. Jika Password Database Kosong di Server:**
Sesuaikan di file `.env`:
```env
database.default.password = password_anda_disini
```

### **3. Jika Tidak Bisa SSH (Upload Semua):**
- Upload **SELURUH folder `vendor/`** ke `/absen/vendor/`
- Upload **SELURUH folder lainnya** sesuai struktur di atas
- Buat manual folder `writable/logs/` dan `writable/cache/` via Plesk File Manager
- Set permission folder tersebut ke `777`

---

## 🧪 TESTING SETELAH UPLOAD:

1. **Akses**: `https://kursuscerdas.com/absen/public/check.php`
   - Cek apakah semua test PASS (✅)
   - Jika masih ada error, screenshot dan kirim ke developer

2. **Akses**: `https://kursuscerdas.com/absen/public/`
   - Cek apakah aplikasi berjalan normal
   - Test login dosen dan mahasiswa

3. **Hapus file debug**:
   - Hapus `public/check.php` setelah testing selesai

---

## 📦 UPLOAD METHODS:

### **Method 1: FTP/SFTP Client (FileZilla, WinSCP)**
- Connect ke server
- Upload folder `vendor/` (ini yang paling besar ~50-100MB)
- Upload file-file lainnya
- Tunggu sampai semua selesai

### **Method 2: Plesk File Manager**
- Login ke Plesk
- File Manager → Upload ZIP file
- Extract di server
- Atau upload per folder

### **Method 3: Git (Jika Ada Access)**
```bash
cd /var/www/vhosts/kursuscerdas.com/httpdocs/absen
git pull origin main
composer install --no-dev --optimize-autoloader
```

---

## 🎯 SUMMARY - YANG HARUS DILAKUKAN:

1. ✅ Upload folder `vendor/` ke server
2. ✅ Upload file `.env` (dengan password DB yang benar)
3. ✅ Upload folder `writable/logs/` dan `writable/cache/` (permission 777)
4. ✅ Upload file `index.php` di root
5. ✅ Upload `app/Config/App.php` dan `app/Config/Filters.php`
6. ✅ Test dengan `check.php`
7. ✅ Hapus `check.php` setelah sukses
8. ✅ Test login mahasiswa dengan NPM: `253510001` password: `253510001`

---

**⚠️ JANGAN LUPA:**
- Password database di `.env` harus sesuai dengan yang ada di server
- Folder `vendor/` adalah yang paling besar dan paling penting
- Setelah sukses, hapus `check.php` dan `debug-login.php` dari folder `public/`

**Good luck! 🚀**
