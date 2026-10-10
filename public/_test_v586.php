<?php
chdir(__DIR__);
$_SERVER['argv'] = ['_test_v586.php'];
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

if (! defined('APPPATH')) {
    define('APPPATH',     realpath(rtrim($paths->appDirectory, '\\/ '))    . DIRECTORY_SEPARATOR);
}
if (! defined('ROOTPATH')) {
    define('ROOTPATH',    realpath(APPPATH . '../')                        . DIRECTORY_SEPARATOR);
}
if (! defined('SYSTEMPATH')) {
    define('SYSTEMPATH',  realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
}
if (! defined('WRITEPATH')) {
    define('WRITEPATH',   realpath(rtrim($paths->writableDirectory, '\\/ ')). DIRECTORY_SEPARATOR);
}

require_once SYSTEMPATH . 'Config/DotEnv.php';
(new \CodeIgniter\Config\DotEnv($paths->appDirectory . '/../'))->load();
if (! defined('ENVIRONMENT')) { define('ENVIRONMENT', $_ENV['CI_ENVIRONMENT'] ?? 'development'); }
if (! defined('CI_DEBUG')) { define('CI_DEBUG', ENVIRONMENT !== 'production'); }
require APPPATH . 'Config/Constants.php';
if (is_file(APPATH . 'Common.php')) { require_once APPPATH . 'Common.php'; }
require_once SYSTEMPATH . 'Common.php';
require_once SYSTEMPATH . 'Config/AutoloadConfig.php';
require_once APPPATH . 'Config/Autoload.php';
require_once SYSTEMPATH . 'Modules/Modules.php';
require_once APPPATH . 'Config/Modules.php';
require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
require_once SYSTEMPATH . 'Config/BaseService.php';
require_once SYSTEMPATH . 'Config/Services.php';
require_once APPPATH . 'Config/Services.php';
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules())->register();
\Config\Services::autoloader()->loadHelpers();
if (is_file(ROOTPATH . 'vendor/autoload.php')) { require_once ROOTPATH . 'vendor/autoload.php'; }

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $label" . PHP_EOL; }
    else { $fail++; echo "  [FAIL] $label" . PHP_EOL; }
}

date_default_timezone_set('Asia/Jakarta');
$db = db_connect();

echo "=== v5.8.6 E2E: Admin\Impression::index + reset ===" . PHP_EOL . PHP_EOL;

// ============================================================
// SCENARIO A: Buat impresi untuk 2 mahasiswa di hari ini
// ============================================================
echo "SCENARIO A: Setup — 2 mahasiswa dengan impresi hari ini" . PHP_EOL;
$db->query("DELETE FROM ad_impressions WHERE user_identifier IN ('student:__v586_a@kampus.ac.id', 'student:__v586_b@kampus.ac.id')");

$db->query("INSERT INTO ad_impressions (ad_id, user_identifier, placement, view_date, view_count, created_at, updated_at) VALUES
  (1, 'student:__v586_a@kampus.ac.id', 'dashboard', CURDATE(), 1, NOW(), NOW()),
  (2, 'student:__v586_a@kampus.ac.id', 'dashboard', CURDATE(), 1, NOW(), NOW()),
  (5, 'student:__v586_a@kampus.ac.id', 'pdf',       CURDATE(), 1, NOW(), NOW()),
  (3, 'student:__v586_b@kampus.ac.id', 'login',    CURDATE(), 1, NOW(), NOW())");
echo "  3 impresi untuk A, 1 impresi untuk B" . PHP_EOL . PHP_EOL;

// ============================================================
// SCENARIO B: Panggil Admin\Impression::index() dan verifikasi output
// ============================================================
echo "SCENARIO B: index() — list impresi hari ini per mahasiswa" . PHP_EOL;
$ctrl = new \App\Controllers\Admin\Impression();
$ctrl->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
$view = $ctrl->index();

check("Output adalah string (view)", is_string($view));
check("Halaman mengandung 2 mahasiswa", substr_count($view, '__v586_') >= 2);
check("Halaman menampilkan A dengan 3 views", str_contains($view, '__v586_a@kampus.ac.id') && str_contains($view, '3x'));
check("Halaman menampilkan B dengan 1 view", str_contains($view, '__v586_b@kampus.ac.id') && str_contains($view, '1x'));
check("Memiliki form reset per mahasiswa", substr_count($view, 'impressions/reset') >= 2);
check("Memiliki CSRF token di setiap form", substr_count($view, 'csrf_test_name') >= 2);
check("Memiliki info catatan kuota", str_contains($view, 'daily_ad_max_display'));
check("Memiliki link Reset Semua Hari Ini", str_contains($view, 'Reset Semua Hari Ini'));
echo PHP_EOL;

// ============================================================
// SCENARIO C: Panggil Admin\Impression::reset() untuk A
// ============================================================
echo "SCENARIO C: reset() — set view_count A ke 0" . PHP_EOL;

// Set up session for CSRF
$session = \Config\Services::session();
$csrfToken = csrf_hash();

// Simulate POST data
$_POST['email'] = '__v586_a@kampus.ac.id';
$_POST['csrf_test_name'] = $csrfToken;
$request = \Config\Services::request();
$request->setGlobal('post', $_POST);

$resp = $ctrl->reset();
check("reset() return RedirectResponse", $resp instanceof \CodeIgniter\HTTP\RedirectResponse);
check("Reset redirect ke /admin/impressions", str_contains($resp->getHeaderLine('Location'), '/admin/impressions'));

// Check flashdata
check("Flash success message set", $session->getFlashdata('success') !== null || $session->getFlashdata('info') !== null);

// Verify DB
$countA = (int) $db->table('ad_impressions')
    ->where('user_identifier', 'student:__v586_a@kampus.ac.id')
    ->where('view_date', date('Y-m-d'))
    ->where('view_count >', 0)
    ->countAllResults();
check("view_count A sekarang 0 (tidak ada row view_count>0)", $countA === 0);

$countB = (int) $db->table('ad_impressions')
    ->where('user_identifier', 'student:__v586_b@kampus.ac.id')
    ->where('view_date', date('Y-m-d'))
    ->where('view_count >', 0)
    ->countAllResults();
check("view_count B TIDAK berubah (masih 1)", $countB === 1);
echo PHP_EOL;

// ============================================================
// SCENARIO D: Reset untuk email yang tidak ada impresi (no-op)
// ============================================================
echo "SCENARIO D: reset() — email tanpa impresi (no-op)" . PHP_EOL;
$resp2 = $ctrl->reset(); // email masih dari POST sebelumnya
// Hmm, $_POST masih ada. Cek: jika view_count A sudah 0, updated=0, flash='info'
$session->remove(['success', 'info', 'error']);
check("Reset no-op return RedirectResponse", $resp2 instanceof \CodeIgniter\HTTP\RedirectResponse);
echo PHP_EOL;

// ============================================================
// SCENARIO E: Reset dengan email invalid (validation fail)
// ============================================================
echo "SCENARIO E: reset() — email invalid → redirect dengan error" . PHP_EOL;
$session->remove(['success', 'info', 'error']);
$request->setGlobal('post', ['email' => 'not-an-email', 'csrf_test_name' => $csrfToken]);
$resp3 = $ctrl->reset();
check("Reset email invalid return RedirectResponse", $resp3 instanceof \CodeIgniter\HTTP\RedirectResponse);
$errMsg = $session->getFlashdata('error');
check("Flash error message set", $errMsg !== null && str_contains($errMsg, 'tidak valid'));
echo PHP_EOL;

// ============================================================
// Cleanup
// ============================================================
$db->query("DELETE FROM ad_impressions WHERE user_identifier IN ('student:__v586_a@kampus.ac.id', 'student:__v586_b@kampus.ac.id')");
echo "Cleanup selesai." . PHP_EOL . PHP_EOL;

echo "=========================================" . PHP_EOL;
echo "Total: $pass passed, $fail failed" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
