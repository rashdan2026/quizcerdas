<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run()
    {
        $defaults = [
            ['setting_key' => 'app_name',                  'setting_value' => 'Sistem Absensi Kampus',    'description' => 'Nama aplikasi'],
            ['setting_key' => 'app_maintenance_mode',      'setting_value' => '1',                       'description' => 'Mode aplikasi: 1 = Aktif, 0 = Maintenance'],
            ['setting_key' => 'default_ad_setting_number', 'setting_value' => '5',                       'description' => 'Modulus detik (1-9) untuk tampil iklan'],
            ['setting_key' => 'daily_ad_max_display',      'setting_value' => '2',                       'description' => 'Maks tampil iklan per user per hari'],
            ['setting_key' => 'ad_lock_duration_seconds',  'setting_value' => '10',                      'description' => 'Durasi modal iklan terkunci (detik)'],
            ['setting_key' => 'ad_max_file_size_mb',       'setting_value' => '1',                       'description' => 'Maks ukuran file GIF iklan (MB)'],
            ['setting_key' => 'admin_login_rate_limit',    'setting_value' => '5',                       'description' => 'Batas gagal login admin sebelum lock'],
            ['setting_key' => 'admin_login_lock_minutes',  'setting_value' => '15',                      'description' => 'Durasi lock admin setelah rate limit (menit)'],
            ['setting_key' => 'student_otp_every_n_logins', 'setting_value' => '5',                      'description' => 'Mahasiswa diminta kode OTP setiap N kali login (reset setelah OTP berhasil)'],
        ];

        foreach ($defaults as $row) {
            $existing = $this->db->table('app_settings')->where('setting_key', $row['setting_key'])->get()->getRowArray();
            if (! $existing) {
                $row['updated_at'] = date('Y-m-d H:i:s');
                $this->db->table('app_settings')->insert($row);
                echo "✓ Setting '{$row['setting_key']}' diinisialisasi.\n";
            }
        }
    }
}
