<?php
/**
 * Pemeriksa schema DB production vs lokal (fitur Registrasi Dosen v9.0).
 * Standalone — tidak butuh CodeIgniter. Letakkan selevel .env, buka via
 * browser, salin seluruh output untuk dibandingkan, lalu HAPUS file ini.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$dir = __DIR__;
$envFile = null;
for ($i = 0; $i < 4; $i++) {
    if (is_file($dir . '/.env')) { $envFile = $dir . '/.env'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if ($envFile === null) { http_response_code(500); exit('ERROR: .env tidak ditemukan.'); }

$cfg = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $cfg[trim($k)] = trim(trim($v), "'\"");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(
        $cfg['database.default.hostname'] ?? 'localhost',
        $cfg['database.default.username'] ?? '',
        $cfg['database.default.password'] ?? '',
        $cfg['database.default.database'] ?? '',
        (int)($cfg['database.default.port'] ?? 3306)
    );
} catch (Throwable $e) { http_response_code(500); exit('ERROR koneksi: ' . htmlspecialchars($e->getMessage())); }

header('Content-Type: text/plain; charset=utf-8');
echo "DATABASE: " . $db->query('SELECT DATABASE()')->fetch_row()[0] . "\n";

foreach (['lecturers', 'lecturer_registrations'] as $t) {
    echo "\n===== SHOW CREATE TABLE $t =====\n";
    try {
        $r = $db->query("SHOW CREATE TABLE `$t`")->fetch_row();
        echo $r[1] . "\n";
    } catch (Throwable $e) {
        echo "TABEL TIDAK ADA / ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n===== DATA DOSEN (tanpa password) =====\n";
try {
    $r = $db->query("SELECT id, nama, email, is_active, email_verified, profile_confirmed, DATE(created_at) created
                     FROM lecturers ORDER BY id");
    while ($row = $r->fetch_assoc()) print_r($row);
} catch (Throwable $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

echo "\n===== MIGRATIONS TERAKHIR =====\n";
try {
    $r = $db->query("SELECT version, batch FROM migrations ORDER BY batch DESC, id DESC LIMIT 5");
    while ($row = $r->fetch_assoc()) print_r($row);
} catch (Throwable $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

echo "\nSELESAI — HAPUS FILE INI DARI SERVER.\n";
