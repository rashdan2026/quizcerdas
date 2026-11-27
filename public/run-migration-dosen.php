<?php
/**
 * Migration mandiri: Registrasi Dosen (v9.0) — TANPA bootstrap CodeIgniter.
 * Letakkan file ini SELEVEL dengan .env (folder root project, mis. /daftar/),
 * buka via browser sekali, verifikasi hasil, lalu HAPUS SEGERA.
 *
 * Idempotent: kolom/tabel yang sudah ada dilewati, aman dijalankan berulang.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 1. Cari .env (naik maksimal 3 folder dari lokasi file ini)
$dir = __DIR__;
$envFile = null;
for ($i = 0; $i < 4; $i++) {
    if (is_file($dir . '/.env')) { $envFile = $dir . '/.env'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if ($envFile === null) {
    http_response_code(500);
    exit('ERROR: file .env tidak ditemukan. Letakkan skrip ini di folder root project (selevel .env).');
}

// 2. Parse konfigurasi database dari .env
$cfg = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $cfg[trim($k)] = trim(trim($v), "'\"");
}
$host = $cfg['database.default.hostname'] ?? 'localhost';
$name = $cfg['database.default.database'] ?? '';
$user = $cfg['database.default.username'] ?? '';
$pass = $cfg['database.default.password'] ?? '';
$port = (int)($cfg['database.default.port'] ?? 3306);

if ($name === '' || $user === '') {
    http_response_code(500);
    exit('ERROR: konfigurasi database.default.* tidak lengkap di .env');
}

// 3. Koneksi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    exit('ERROR koneksi database: ' . htmlspecialchars($e->getMessage()));
}

function columnExists(mysqli $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_row()[0] > 0;
}

function tableExists(mysqli $db, string $table): bool {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_row()[0] > 0;
}

echo '<pre style="font:14px/1.6 monospace">';
echo "Database: " . htmlspecialchars($name) . " @ " . htmlspecialchars($host) . "\n";
echo str_repeat('=', 60) . "\n";

// 4. Kolom baru di lecturers (idempotent)
$columns = [
    'jenis_kelamin' => "ALTER TABLE lecturers ADD COLUMN jenis_kelamin ENUM('Laki-Laki','Perempuan') NULL AFTER email",
    'tanggal_lahir' => "ALTER TABLE lecturers ADD COLUMN tanggal_lahir DATE NULL AFTER jenis_kelamin",
    'nama_kampus'   => "ALTER TABLE lecturers ADD COLUMN nama_kampus VARCHAR(100) NULL AFTER tanggal_lahir",
    'email_verified' => "ALTER TABLE lecturers ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active",
    'profile_confirmed' => "ALTER TABLE lecturers ADD COLUMN profile_confirmed TINYINT(1) NOT NULL DEFAULT 1 AFTER email_verified",
    'profile_confirmed_at' => "ALTER TABLE lecturers ADD COLUMN profile_confirmed_at DATETIME NULL AFTER profile_confirmed",
];

foreach ($columns as $col => $sql) {
    if (columnExists($db, 'lecturers', $col)) {
        echo "[LEWAT] lecturers.$col sudah ada\n";
    } else {
        try {
            $db->query($sql);
            echo "[OK]    lecturers.$col ditambahkan (default aman untuk akun lama)\n";
        } catch (Throwable $e) {
            echo "[GAGAL] lecturers.$col : " . htmlspecialchars($e->getMessage()) . "\n";
        }
    }
}

// Pastikan dosen lama tidak terkunci (idempotent, hanya update baris yang 0/NULL)
$db->query("UPDATE lecturers SET email_verified = 1 WHERE email_verified IS NULL OR email_verified = 0 AND is_active = 1 AND created_at < '2026-11-27'");
echo "[OK]    dosen lama (sebelum 2026-11-27) dipastikan email_verified = 1\n";

// 5. Tabel pending registrasi (idempotent)
if (tableExists($db, 'lecturer_registrations')) {
    echo "[LEWAT] tabel lecturer_registrations sudah ada\n";
} else {
    $db->query("CREATE TABLE lecturer_registrations (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        jenis_kelamin ENUM('Laki-Laki','Perempuan') NOT NULL,
        tanggal_lahir DATE NOT NULL,
        nama_kampus VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        verify_code_hash VARCHAR(255) NOT NULL,
        verify_expires_at DATETIME NOT NULL,
        verify_attempts INT(11) NOT NULL DEFAULT 0,
        resend_available_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NULL DEFAULT NULL,
        updated_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "[OK]    tabel lecturer_registrations dibuat\n";
}

// 6. Verifikasi akun yang dilaporkan bermasalah
echo str_repeat('=', 60) . "\n";
$res = $db->query("SELECT email, is_active, email_verified, profile_confirmed
                   FROM lecturers WHERE email = 'hendra@eng.uir.ac.id'");
if ($row = $res->fetch_assoc()) {
    echo "Verifikasi hendra@eng.uir.ac.id:\n";
    echo "  is_active={$row['is_active']} email_verified={$row['email_verified']} profile_confirmed={$row['profile_confirmed']}\n";
    echo ($row['is_active'] == 1 && $row['email_verified'] == 1 && $row['profile_confirmed'] == 1)
        ? "  => AKUN SIAP LOGIN. Silakan coba login kembali.\n"
        : "  => Masih bermasalah, kirim output ini ke developer.\n";
} else {
    echo "Email hendra@eng.uir.ac.id TIDAK ditemukan di tabel lecturers production.\n";
}

echo "\nSELESAI. HAPUS FILE INI DARI SERVER SEKARANG.\n";
echo '</pre>';
