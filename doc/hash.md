# Password Hashing & Comparison

## Algoritma yang Digunakan

Aplikasi ini menggunakan **bcrypt** via fungsi native PHP:

| Fungsi | Algoritma | Keterangan |
|--------|-----------|------------|
| `password_hash()` | Bcrypt (`PASSWORD_BCRYPT`) | Untuk hashing password baru |
| `password_verify()` | Bcrypt | Untuk memverifikasi password saat login |

## Cara Kerja

### 1. Hashing Password Baru
```php
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
```
- Menggunakan blowfish cipher dengan cost factor default (10)
- Menghasilkan hash sepanjang 60 karakter (compatible dengan VARCHAR 255)

### 2. Verifikasi Password saat Login
```php
password_verify($password, $storedPassword);
```
- Membandingkan password plain dengan hash tersimpan
- Menggunakan timing-safe comparison untuk mencegah timing attack

### 3. Fallback untuk Legacy Data
```php
// Jika stored password belum menggunakan bcrypt (dimulai dengan $2)
// maka fallback ke hash_equals untuk legacy MD5/sha1
str_starts_with($storedPassword, '$2')
    ? password_verify($password, $storedPassword)
    : hash_equals($storedPassword, $password);
```

## File yang Relevan

- `app/Controllers/Auth.php` - Login & change password
- `app/Controllers/Profile.php` - Change password via profile
- `app/Database/Seeds/AppSeeder.php` - Seed data dengan bcrypt

## Keamanan

1. **Timing-safe comparison** - `password_verify()` menggunakan perbandingan yang aman terhadap timing attack
2. **Cost factor** - Cost bcrypt default (10) dapat ditingkatkan via `PASSWORD_BCRYPT`, ['cost' => 12]`
3. **Legacy handling** - Sistem fallback untuk data lama yang belum di-migrasi ke bcrypt
