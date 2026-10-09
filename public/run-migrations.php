<?php
declare(strict_types=1);

/*
 * Migration runner via browser.
 * Access: https://localhost/DB2/absen2/public/run-migrations.php
 *
 * Catatan: file ini untuk development/admin. Hapus setelah setup selesai.
 */

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

// Define path constants manual
define('APPPATH',     realpath(rtrim($paths->appDirectory, '\\/ '))    . DIRECTORY_SEPARATOR);
define('ROOTPATH',    realpath(APPPATH . '../')                        . DIRECTORY_SEPARATOR);
define('SYSTEMPATH',  realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH',   realpath(rtrim($paths->writableDirectory, '\\/ ')). DIRECTORY_SEPARATOR);

// Load .env pakai DotEnv CI4 (populate $_ENV, $_SERVER, putenv)
require_once SYSTEMPATH . 'Config/DotEnv.php';
$envDir = $paths->envDirectory ?? $paths->appDirectory . '/../';
(new \CodeIgniter\Config\DotEnv($envDir))->load();

if (! defined('ENVIRONMENT')) {
    $env = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';
    define('ENVIRONMENT', $env);
}
if (! defined('CI_DEBUG')) {
    define('CI_DEBUG', ENVIRONMENT !== 'production');
}

// Load constants + common + autoloader
require APPPATH . 'Config/Constants.php';

if (is_file(APPPATH . 'Common.php')) {
    require_once APPPATH . 'Common.php';
}
require_once SYSTEMPATH . 'Common.php';

require_once SYSTEMPATH . 'Config/AutoloadConfig.php';
require_once APPPATH . 'Config/Autoload.php';
require_once SYSTEMPATH . 'Modules/Modules.php';
require_once APPPATH . 'Config/Modules.php';

require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
require_once SYSTEMPATH . 'Config/BaseService.php';
require_once SYSTEMPATH . 'Config/Services.php';
require_once APPPATH . 'Config/Services.php';

\Config\Services::autoloader()
    ->initialize(new \Config\Autoload(), new \Config\Modules())
    ->register();
\Config\Services::autoloader()->loadHelpers();

if (is_file(ROOTPATH . 'vendor/autoload.php')) {
    require_once ROOTPATH . 'vendor/autoload.php';
}

$db   = db_connect();
$seed = $_GET['seed'] ?? '';

?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Database Migration Runner</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 800px; margin: 30px auto; padding: 0 20px; color: #1E293B; }
        h1 { font-size: 1.4rem; border-bottom: 2px solid #E2E8F0; padding-bottom: 8px; }
        h2 { font-size: 1.05rem; margin-top: 24px; color: #475569; }
        .ok { color: #059669; }
        .err { color: #DC2626; }
        .warn { color: #D97706; }
        .btn { display: inline-block; padding: 8px 14px; background: #4F46E5; color: #fff; text-decoration: none; border-radius: 6px; font-size: .9rem; margin: 2px; }
        .btn:hover { background: #3730A3; }
        .btn-gray { background: #64748B; }
        .btn-gray:hover { background: #475569; }
        ul { background: #F8FAFC; padding: 12px 12px 12px 28px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: .85rem; }
        pre { background: #1E293B; color: #E2E8F0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: .8rem; white-space: pre-wrap; }
        hr { border: 0; border-top: 1px solid #E2E8F0; margin: 24px 0; }
        .panel { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; margin: 10px 0; }
    </style>
</head>
<body>

<h1>🛠 Database Migration Runner</h1>
<p><small>Environment: <strong><?= esc(ENVIRONMENT) ?></strong> · Database: <strong><?= esc($db->getDatabase()) ?></strong></small></p>

<?php
// === 1. CEK TABEL EXISTING ===
echo "<h2>1. Status Tabel Existing</h2>";
$tables = ['lecturers', 'subjects', 'students', 'meetings', 'attendance', 'otp_codes', 'pdf_files', 'admins', 'ads', 'ad_impressions', 'app_settings'];
echo "<ul>";
foreach ($tables as $table) {
    try {
        $exists = $db->query(
            "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$db->getDatabase(), $table]
        )->getRowArray()['cnt'] > 0;
        $cls = $exists ? 'ok' : 'warn';
        $icon = $exists ? '✅' : '⚠️';
        echo "<li class='{$cls}'>{$icon} <strong>{$table}</strong>: " . ($exists ? 'EXISTS' : 'MISSING') . "</li>";
    } catch (Throwable $e) {
        echo "<li class='err'>❌ <strong>{$table}</strong>: error - " . esc($e->getMessage()) . "</li>";
    }
}
echo "</ul>";

// === 2. STATUS MIGRASI (dideklarasikan, dipanggil di akhir halaman setelah semua aksi) ===
$renderMigrationStatus = function() {
    echo "<h2>6. Migration Status (Real-time)</h2>";
    echo "<p><small>Status di bawah ini adalah state TERKINI setelah aksi di atas (jika ada).</small></p>";
    try {
        $migration = \Config\Services::migrations();
        $migration->setNamespace(null);
        $history = $migration->getHistory();
        $ran     = [];
        foreach ($history as $h) {
            $ran[$h->version] = $h;
        }
        $files = glob(APPPATH . 'Database/Migrations/*.php');
        sort($files);
        $pendingCount = 0;
        echo "<ul>";
        foreach ($files as $f) {
            $name   = basename($f, '.php');
            $prefix = substr($name, 0, strpos($name, '_'));  // ambil sampai underscore pertama
            $matched = null;
            if (isset($ran[$name])) {
                $matched = $ran[$name];
            } elseif (isset($ran[$prefix])) {
                $matched = $ran[$prefix];
            }
            if ($matched) {
                echo "<li class='ok'>✅ {$name} <small class='warn'>(batch {$matched->batch})</small></li>";
            } else {
                $pendingCount++;
                echo "<li class='warn'>⏳ {$name} <small>(pending)</small></li>";
            }
        }
        echo "</ul>";
        if ($pendingCount === 0) {
            echo "<p class='ok'>✓ Semua migration sudah dijalankan. Tidak ada pending.</p>";
        } else {
            echo "<p class='warn'>⚠ Ada {$pendingCount} migration pending. Klik tombol di section 3 untuk menjalankan.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='err'>Error cek status: " . esc($e->getMessage()) . "</p>";
    }
};

// === 3. JALANKAN MIGRASI (klik tombol) ===
echo "<h2>3. Jalankan Migrasi Pending</h2>";
echo "<div class='panel'>";
echo "<p>Tombol ini akan menjalankan SEMUA migration yang belum dijalankan (termasuk 4 migration baru untuk Admin & Iklan).</p>";
echo "<a class='btn' href='?run=1'>▶ Jalankan Migrasi</a> ";
echo "<a class='btn btn-gray' href='" . $_SERVER['PHP_SELF'] . "'>↻ Refresh</a>";
echo "</div>";

if (isset($_GET['run'])) {
    echo "<h2>3a. Hasil Migrasi</h2>";
    try {
        // Tandai migrasi lama sebagai "sudah dijalankan" agar latest() hanya eksekusi yang baru.
        // Ini untuk kasus di mana tabel existing sudah ada (di-create manual / via run-migrations versi lama)
        // tapi tabel migrations kosong atau belum mencatat semua migrasi lama.
        $oldVersions = [
            '2026-04-05-000001_CreateAttendanceSystem',
            '2026-04-05-000002_AlterStudentsForDb2Sync',
            '2026-04-05-000003_AddLinkPdfToMeetings',
            '2026-04-05-000004_CreatePdfFilesTable',
            '2026-04-05-000005_AlterMeetingsForPdfFileRef',
            '2026-04-08-000001_AddLoginCountToStudents',
            '2026-04-08-000002_ChangeStudentsPrimaryKeyToEmail',
        ];
        try {
            $existing = $db->table('migrations')->countAllResults();
        } catch (Throwable $e) {
            $existing = 0;
        }
        if ($existing === 0) {
            $now = time();
            foreach ($oldVersions as $v) {
                $db->table('migrations')->insert([
                    'version'   => $v,
                    'class'     => 'App\\Database\\Migrations\\' . str_replace('_', '', substr($v, 18)),
                    'group'     => 'default',
                    'namespace' => 'App',
                    'time'      => $now,
                    'batch'     => 1,
                ]);
            }
            echo "<p class='ok'>✓ Tandai 7 migrasi lama sebagai sudah dijalankan (batch 1).</p>";
        } else {
            // Tambahkan entry untuk migrasi lama yang belum tercatat (mis. 2026-04-08-000002 yg sempat gagal)
            $knownVersions = array_column($db->table('migrations')->get()->getResultArray(), 'version');
            $now = time();
            $added = 0;
            foreach ($oldVersions as $v) {
                if (! in_array($v, $knownVersions, true)) {
                    $db->table('migrations')->insert([
                        'version'   => $v,
                        'class'     => 'App\\Database\\Migrations\\' . str_replace('_', '', substr($v, 18)),
                        'group'     => 'default',
                        'namespace' => 'App',
                        'time'      => $now,
                        'batch'     => 99,
                    ]);
                    $added++;
                }
            }
            if ($added > 0) {
                echo "<p class='ok'>✓ Tambahkan {$added} entry migrasi lama yang belum tercatat (batch 99).</p>";
            }
        }

        $migration = \Config\Services::migrations();
        $migration->setNamespace(null);
        $result = $migration->latest();
        if ($result) {
            echo "<p class='ok'>✅ Migrasi berhasil dijalankan.</p>";
            $history = $migration->getHistory();
            echo "<ul>";
            foreach ($history as $item) {
                echo "<li>{$item->group}: {$item->version} (batch {$item->batch})</li>";
            }
            echo "</ul>";
        } else {
            $err = $migration->getError();
            echo "<p class='err'>❌ Migrasi gagal: " . esc($err ?? 'unknown error') . "</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='err'>❌ Error: " . esc($e->getMessage()) . "</p>";
        echo "<pre>" . esc($e->getTraceAsString()) . "</pre>";
    }
}

// === 4. SEEDER ===
echo "<h2>4. Jalankan Seeder</h2>";
echo "<div class='panel'>";
echo "<p>Seeder membuat data default (admin user & app settings).</p>";
echo "<a class='btn' href='?seed=1'>🌱 Seed Admin Default</a> ";
echo "<a class='btn' href='?seed=2'>🌱 Seed App Settings</a> ";
echo "<a class='btn' href='?seed=all'>🌱 Seed Semua</a>";
echo "</div>";

if ($seed !== '') {
    echo "<h2>4a. Hasil Seeder</h2>";
    echo "<pre>";
    $seedMap = [
        '1' => ['AdminSeeder',      'app/Database/Seeds/AdminSeeder.php'],
        '2' => ['AppSettingSeeder', 'app/Database/Seeds/AppSettingSeeder.php'],
    ];
    $seedList = $seed === 'all' ? array_keys($seedMap) : [$seed];

    foreach ($seedList as $k) {
        if (! isset($seedMap[$k])) continue;
        [$class, $file] = $seedMap[$k];
        $path = APPPATH . 'Database/Seeds/' . basename($file);
        if (! is_file($path)) {
            echo "✗ {$class} ERROR: file tidak ditemukan ({$path})\n\n";
            continue;
        }
        require_once $path;
        $fqcn = '\\App\\Database\\Seeds\\' . $class;
        echo "▶ Running {$fqcn}...\n";
        try {
            $instance = new $fqcn(new \Config\Database(), $db);
            $instance->run();
            echo "✓ {$class} selesai.\n\n";
        } catch (Throwable $e) {
            echo "✗ {$class} ERROR: " . $e->getMessage() . "\n\n";
        }
    }
    echo "</pre>";
}

// === 5. CREDENTIALS DEFAULT ===
echo "<hr>";
echo "<h2>5. Default Admin (setelah seed)</h2>";
echo "<div class='panel'>";
echo "<p><strong>Email:</strong> <code>admin@kampus.ac.id</code><br>";
echo "<strong>Password:</strong> <code>admin123</code></p>";
echo "<p class='warn'>⚠️ Segera ganti password setelah login pertama!</p>";
echo "<p>🔗 Login: <a class='btn' href='/admin/login'>/admin/login</a></p>";
echo "</div>";

echo "<hr>";
echo "<p><small>File ini untuk development. Hapus <code>public/run-migrations.php</code> setelah setup selesai.</small></p>";

// === 6. MIGRATION STATUS (real-time, SELALU di akhir setelah semua aksi) ===
$renderMigrationStatus();

?>

</body>
</html>
