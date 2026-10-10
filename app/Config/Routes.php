<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->match(['get', 'post'], 'auth', 'Auth::index');
$routes->post('auth/login', 'Auth::login');

$routes->get('register', 'Registration::index');
$routes->post('register', 'Registration::register');
$routes->get('register/verify-email', 'Registration::verifyEmail');
$routes->post('register/verify-email', 'Registration::confirmVerify');
$routes->post('register/resend-otp', 'Registration::resendOtp');
$routes->match(['get', 'post'], 'auth/otp', 'Auth::otp');
$routes->match(['get', 'post'], 'auth/change-password', 'Auth::changePassword');
$routes->match(['get', 'post'], 'auth/forgot-password', 'Auth::forgotPassword');
$routes->get('logout', 'Auth::logout');

$routes->get('media/gfx/(:any)', 'AdImage::serve/$1');
$routes->get('ad/click/(:num)', 'AdClick::track/$1');
$routes->get('maintenance', function () { return view('maintenance', ['appName' => (new \App\Models\AppSettingModel())->getValue('app_name', 'Sistem Absensi Kampus')]); });

$routes->group('profile', ['filter' => 'authguard'], static function ($routes) {
    $routes->get('/', 'Profile::index');
    $routes->post('change-password', 'Profile::changePassword');
});

$routes->group('admin', static function ($routes) {
    $routes->match(['get', 'post'], 'login', 'Admin\Auth::login');
    $routes->get('logout', 'Admin\Auth::logout');

    $routes->group('', ['filter' => 'authguard:admin'], static function ($routes) {
        $routes->get('/', 'Admin\Dashboard::index');
        $routes->get('dashboard', 'Admin\Dashboard::index');

        $routes->get('ads', 'Admin\Ads::index');
        $routes->get('ads/click-report', 'Admin\Ads::clickReport');
        $routes->get('ads/create', 'Admin\Ads::create');
        $routes->get('ads/(:num)/edit', 'Admin\Ads::edit/$1');
        $routes->post('ads/save', 'Admin\Ads::save');
        $routes->post('ads/(:num)/toggle', 'Admin\Ads::toggle/$1');
        $routes->post('ads/(:num)/delete', 'Admin\Ads::delete/$1');
        $routes->post('ads/reset-impressions', 'Admin\Ads::resetImpressions');

        $routes->get('lecturers', 'Admin\Lecturer::index');
        $routes->get('lecturers/create', 'Admin\Lecturer::create');
        $routes->get('lecturers/(:num)/edit', 'Admin\Lecturer::edit/$1');
        $routes->post('lecturers/save', 'Admin\Lecturer::save');
        $routes->post('lecturers/(:num)/toggle', 'Admin\Lecturer::toggle/$1');
        $routes->post('lecturers/(:num)/reset-password', 'Admin\Lecturer::resetPassword/$1');

        $routes->get('students', 'Admin\Student::index');
        $routes->get('students/(:any)/edit', 'Admin\Student::edit/$1');
        $routes->post('students/save', 'Admin\Student::save');
        $routes->post('students/(:any)/reset-password', 'Admin\Student::resetPassword/$1');
        $routes->post('students/reset-all-otp-counters', 'Admin\Student::resetAllOtpCounters');

        // v5.8.6: Kuota Iklan Mahasiswa — halaman admin impressions per mahasiswa
        $routes->get('impressions', 'Admin\Impression::index');
        $routes->post('impressions/reset', 'Admin\Impression::reset');

        $routes->get('settings', 'Admin\Settings::index');
        $routes->post('settings/save', 'Admin\Settings::save');

        $routes->match(['get', 'post'], 'change-password', 'Admin\Dashboard::changePassword');
    });
});

$routes->group('lecturer', ['filter' => 'authguard:lecturer'], static function ($routes) {
    $routes->get('subjects', 'Lecturer\Subject::index');
    $routes->get('subjects/export/(:num)', 'Lecturer\Subject::exportXls/$1');
    $routes->match(['get', 'post'], 'subjects/create', 'Lecturer\Subject::create');
    $routes->post('subjects/(:num)/toggle', 'Lecturer\Subject::toggle/$1');

    $routes->get('pdf-manager', 'Lecturer\PdfManager::index');
    $routes->post('pdf-manager/upload', 'Lecturer\PdfManager::upload');
    $routes->post('pdf-manager/delete/(:num)', 'Lecturer\PdfManager::delete/$1');

    $routes->get('meetings', 'Lecturer\Meeting::index');
    $routes->match(['get', 'post'], 'meetings/create', 'Lecturer\Meeting::create');
    $routes->get('meetings/(:num)', 'Lecturer\Meeting::show/$1');
    $routes->post('meetings/(:num)/token', 'Lecturer\Meeting::refreshToken/$1');
    $routes->match(['get', 'post'], 'meetings/edit/(:num)', 'Lecturer\Meeting::edit/$1');
    $routes->post('meetings/delete/(:num)', 'Lecturer\Meeting::delete/$1');

    $routes->get('report', 'Lecturer\Report::index');
    $routes->get('report/detail/(:num)', 'Lecturer\Report::detail/$1');
    $routes->post('report/add', 'Lecturer\Report::addManual');
});

$routes->group('student', ['filter' => 'authguard:student'], static function ($routes) {
    $routes->get('dashboard', 'Student\Dashboard::index');
    $routes->match(['get', 'post'], 'dashboard/edit-profile', 'Student\Dashboard::editProfile');
    $routes->post('dashboard/update-profile', 'Student\Dashboard::updateProfile');
    $routes->match(['get', 'post'], 'scan', 'Student\Scan::index');
    $routes->post('scan/submit', 'Student\Scan::submit');
    $routes->get('detail/(:num)', 'Student\Scan::detail/$1');
    $routes->get('meeting/info/(:num)', 'Student\Scan::meetingInfo/$1');
    $routes->get('scan/validate-token', 'Student\Scan::validateToken');
    $routes->get('scan/lookup-token',   'Student\Scan::lookupToken');
    $routes->get('pdf/(:num)', 'Student\PdfViewer::index/$1');
    $routes->get('pdf/file/(:num)', 'Student\PdfFile::view/$1');
    $routes->get('meeting/(:num)/video', 'Student\Scan::video/$1');
});
