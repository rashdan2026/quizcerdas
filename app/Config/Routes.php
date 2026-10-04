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

$routes->group('profile', ['filter' => 'authguard'], static function ($routes) {
    $routes->get('/', 'Profile::index');
    $routes->post('change-password', 'Profile::changePassword');
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
    $routes->get('dashboard/edit-profile', 'Student\Dashboard::editProfile');
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
