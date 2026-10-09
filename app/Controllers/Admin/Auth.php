<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AppSettingModel;

class Auth extends BaseController
{
    protected AdminModel $adminModel;
    protected AppSettingModel $settingModel;

    public function __construct()
    {
        $this->adminModel   = new AdminModel();
        $this->settingModel = new AppSettingModel();
    }

    public function login()
    {
        if (session('logged_in') && session('role') === 'admin') {
            return redirect()->to('/admin/dashboard');
        }

        $session = session();
        $maxAttempts = (int) $this->settingModel->getValue('admin_login_rate_limit', 5);
        $lockMinutes = (int) $this->settingModel->getValue('admin_login_lock_minutes', 15);

        if ($this->request->getMethod() === 'POST') {
            $identifier = trim((string) $this->request->getPost('identifier'));
            $password   = (string) $this->request->getPost('password');

            $attemptsKey = 'admin_login_attempts_' . md5($identifier);
            $lockKey     = 'admin_login_locked_until_' . md5($identifier);

            $lockedUntil = $session->get($lockKey);
            if ($lockedUntil && time() < (int) $lockedUntil) {
                $wait = (int) $lockedUntil - time();
                return redirect()->back()->withInput()->with('error', "Terlalu banyak percobaan. Coba lagi dalam {$wait} detik.");
            }

            if ($identifier === '' || $password === '') {
                return redirect()->back()->withInput()->with('error', 'Email dan password wajib diisi.');
            }

            $admin = $this->adminModel->findByEmail($identifier);
            if (! $admin || (int) $admin['is_active'] !== 1 || ! password_verify($password, $admin['password'])) {
                $attempts = (int) $session->get($attemptsKey, 0) + 1;
                $session->set($attemptsKey, $attempts);
                if ($attempts >= $maxAttempts) {
                    $session->set($lockKey, time() + ($lockMinutes * 60));
                    $session->remove($attemptsKey);
                    return redirect()->back()->withInput()->with('error', "Akun terkunci sementara selama {$lockMinutes} menit karena terlalu banyak percobaan gagal.");
                }
                return redirect()->back()->withInput()->with('error', 'Email atau password salah.');
            }

            $session->remove($attemptsKey);
            $session->remove($lockKey);

            $this->adminModel->update($admin['id'], ['last_login' => date('Y-m-d H:i:s')]);

            $session->set([
                'logged_in'  => true,
                'role'       => 'admin',
                'user_id'    => (int) $admin['id'],
                'user_name'  => $admin['nama'],
                'user_email' => $admin['email'],
            ]);

            return redirect()->to('/admin/dashboard')->with('success', 'Selamat datang, ' . $admin['nama'] . '.');
        }

        return view('admin/auth/login');
    }

    public function logout()
    {
        $session = session();
        $session->remove(['logged_in', 'role', 'user_id', 'user_name', 'user_email']);
        return redirect()->to('/admin/login')->with('info', 'Anda telah logout.');
    }
}
