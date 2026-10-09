<?php

namespace App\Filters;

use App\Models\AppSettingModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class MaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Admin role selalu punya akses penuh, walau dalam mode maintenance
        if (session('role') === 'admin') {
            return;
        }

        // Cek setting mode maintenance
        $settingModel  = new AppSettingModel();
        $maintenanceOn = $settingModel->getValue('app_maintenance_mode', '1') === '0';

        if (! $maintenanceOn) {
            return; // aplikasi aktif, lanjut ke controller
        }

        // Pakai URI path lengkap + path relatif baseURL, agar robust terhadap baseURL
        $fullPath  = $request->getUri()->getPath();
        $basePath  = $request->getPath();

        // Izinkan akses ke path admin (URL '/admin/...')
        if (str_starts_with($basePath, '/admin') || str_contains($fullPath, '/admin')) {
            return;
        }

        // Izinkan akses ke halaman maintenance itu sendiri (biar tidak loop)
        if (str_ends_with($basePath, '/maintenance') || str_contains($fullPath, '/maintenance')) {
            return;
        }

        // Izinkan logout (supaya user bisa keluar)
        if (str_contains($fullPath, '/logout')) {
            return;
        }

        // Tampilkan halaman maintenance (HTTP 503 Service Unavailable)
        $appName   = $settingModel->getValue('app_name', 'Sistem Absensi Kampus');
        $response  = service('response');
        $response->setStatusCode(503);
        $response->setHeader('Retry-After', '3600');
        $response->setBody(view('maintenance', ['appName' => $appName]));
        return $response;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada logika after
    }
}
