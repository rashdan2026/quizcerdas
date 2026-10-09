<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AdService;
use App\Models\AppSettingModel;

class Settings extends BaseController
{
    protected AppSettingModel $model;
    protected AdService $adService;

    public function __construct()
    {
        $this->model     = new AppSettingModel();
        $this->adService = new AdService();
    }

    private function defaults(): array
    {
        return [
            'app_name'                    => ['Sistem Absensi Kampus', 'Nama aplikasi'],
            'app_maintenance_mode'        => ['1', 'Mode aplikasi: 1 = Aktif, 0 = Maintenance (halaman maintenance untuk user, admin tetap bisa akses)'],
            'default_ad_setting_number'   => ['5', 'Modulus detik (1-9) untuk tampil iklan'],
            'daily_ad_max_display'        => ['2', 'Maks tampil iklan per user per hari'],
            'ad_lock_duration_seconds'    => ['10', 'Durasi modal iklan terkunci (detik)'],
            'ad_max_file_size_mb'         => ['1', 'Maks ukuran file GIF iklan (MB)'],
            'admin_login_rate_limit'      => ['5', 'Batas gagal login admin sebelum lock'],
            'admin_login_lock_minutes'    => ['15', 'Durasi lock admin setelah rate limit (menit)'],
            'student_otp_every_n_logins'  => ['5', 'Mahasiswa diminta kode OTP setiap N kali login (counter reset setelah OTP berhasil)'],
        ];
    }

    public function index()
    {
        $defaults = $this->defaults();
        $rows = $this->model->getAllAsArray();
        $settings = [];
        foreach ($defaults as $key => [$default, $desc]) {
            $settings[$key] = [
                'value'       => $rows[$key]['setting_value'] ?? $default,
                'description' => $rows[$key]['description']    ?? $desc,
                'is_default'  => ! isset($rows[$key]),
            ];
        }

        $data = [
            'pageTitle'    => 'Pengaturan Aplikasi',
            'pageSubtitle' => 'Konfigurasi sistem & iklan',
            'settings'     => $settings,
        ];
        return view('admin/settings/index', $data);
    }

    public function save()
    {
        $post = $this->request->getPost('settings');
        if (! is_array($post)) {
            return redirect()->to('/admin/settings')->with('error', 'Data tidak valid.');
        }
        $defaults = $this->defaults();
        $saved = 0;
        foreach ($post as $key => $value) {
            if (! isset($defaults[$key])) {
                continue;
            }
            $desc = $defaults[$key][1];
            if ($key === 'default_ad_setting_number') {
                $value = max(1, min(9, (int) $value));
            } elseif (in_array($key, ['daily_ad_max_display', 'ad_lock_duration_seconds', 'ad_max_file_size_mb', 'admin_login_rate_limit', 'admin_login_lock_minutes', 'student_otp_every_n_logins'], true)) {
                $value = max(0, (int) $value);
            }
            $this->model->setValue($key, (string) $value, $desc);
            $saved++;
        }
        return redirect()->to('/admin/settings')->with('success', "{$saved} pengaturan disimpan.");
    }
}
