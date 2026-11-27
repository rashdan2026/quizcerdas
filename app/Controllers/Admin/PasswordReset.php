<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\PasswordResetService;

/**
 * Pengaturan Keamanan Email — reset password (v11.0). Admin-only.
 * Semua perubahan divalidasi & dicatat di password_reset_audit.
 */
class PasswordReset extends BaseController
{
    private const RANGES = [
        'max_requests'      => [1, 10],
        'delay_2_minutes'   => [1, 120],
        'delay_3_minutes'   => [1, 120],
        'delay_4_minutes'   => [1, 120],
        'token_ttl_minutes' => [5, 60],
        'ip_max_per_hour'   => [5, 1000],
    ];

    public function index()
    {
        $service = new PasswordResetService();
        $db      = db_connect();

        $audit = $db->table('password_reset_audit')
            ->orderBy('id', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();

        return view('admin/password_reset/index', [
            'pageTitle'    => 'Pengaturan Keamanan Email',
            'pageSubtitle' => 'Reset password & pembatasan permintaan',
            'settings'     => $service->settings(),
            'audit'        => $audit,
        ]);
    }

    public function save()
    {
        $data = [];
        foreach (self::RANGES as $key => [$min, $max]) {
            $val = (int) $this->request->getPost($key);
            if ($val < $min || $val > $max) {
                return redirect()->to('/admin/password-reset-settings')
                    ->with('error', 'Nilai ' . $key . ' harus antara ' . $min . ' dan ' . $max . '.');
            }
            $data[$key] = $val;
        }

        $admin = (new \App\Models\AdminModel())->find(session('user_id'));

        (new PasswordResetService())->updateSettings(
            $data,
            (int) session('user_id'),
            (string) ($admin['email'] ?? '')
        );

        return redirect()->to('/admin/password-reset-settings')
            ->with('success', 'Pengaturan keamanan email disimpan. Token yang sudah diterbitkan tetap mengikuti masa berlaku saat diterbitkan.');
    }
}
