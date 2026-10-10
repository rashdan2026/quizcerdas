<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AdImpressionModel;
use App\Models\AdModel;
use App\Models\LecturerModel;
use App\Models\MeetingModel;
use App\Models\StudentModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $stats = [
            'students'   => (new StudentModel())->countAll(),
            'lecturers'  => (new LecturerModel())->where('is_active', 1)->countAllResults(),
            'ads_active' => (new AdModel())->where('is_active', 1)->countAllResults(),
            'meetings'   => (new MeetingModel())->countAll(),
        ];

        $recentLogins = [];
        try {
            $adminLogins = (new AdminModel())
                ->select('email, last_login as when_col, "admin" as role')
                ->where('last_login IS NOT NULL', null, false)
                ->orderBy('last_login', 'DESC')
                ->limit(5)
                ->findAll();
            foreach ($adminLogins as $r) {
                $recentLogins[] = [
                    'when'  => $r['when_col'] ? date('d M H:i', strtotime($r['when_col'])) : '-',
                    'email' => $r['email'],
                    'role'  => $r['role'],
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $data = [
            'pageTitle'    => 'Dashboard',
            'pageSubtitle' => 'Ringkasan sistem',
            'stats'        => $stats,
            'recentLogins' => $recentLogins,
        ];

        return view('admin/dashboard', $data);
    }

    /**
     * GET/POST ganti password admin yang sedang login.
     * - Verifikasi password lama (bcrypt)
     * - Validasi password baru (min 8 char, harus sama dengan konfirmasi)
     * - Hash bcrypt + simpan ke tabel admins
     * - Kirim email notifikasi ke admin berisi password baru (agar admin
     *   tidak lupa jika browser tidak remember).
     */
    public function changePassword()
    {
        $session    = session();
        $adminId    = (int) $session->get('user_id');
        $adminEmail = (string) $session->get('user_email');

        if (! $adminId || $session->get('role') !== 'admin') {
            return redirect()->to('/admin/login')->with('error', 'Sesi admin tidak valid.');
        }

        $adminModel = new AdminModel();
        $admin      = $adminModel->find($adminId);
        if (! $admin) {
            return redirect()->to('/admin/logout');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('admin/change_password', [
                'pageTitle'    => 'Ganti Password',
                'pageSubtitle' => 'Ubah password akun admin Anda',
                'admin'        => $admin,
            ]);
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        // 1) Verifikasi password lama
        if ($currentPassword === '' || ! password_verify($currentPassword, (string) $admin['password'])) {
            return redirect()->back()->with('error', 'Password lama salah.');
        }

        // 2) Validasi password baru
        if (strlen($newPassword) < 8) {
            return redirect()->back()->with('error', 'Password baru minimal 8 karakter.');
        }
        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->with('error', 'Konfirmasi password tidak sama.');
        }

        // 3) Hash + simpan
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $adminModel->update($adminId, ['password' => $hash]);

        // 4) Kirim email notifikasi berisi password baru
        $mailResult = $this->sendPasswordChangeEmail(
            $admin['email'],
            $admin['nama'],
            $newPassword
        );

        $msg = 'Password berhasil diperbarui.';
        if ($mailResult['ok']) {
            $msg .= ' Password baru juga telah dikirim ke email Anda sebagai catatan.';
        } else {
            $msg .= ' (Catatan: email notifikasi gagal terkirim — ' . $mailResult['message'] . ').';
        }

        return redirect()->to('/admin/change-password')->with('success', $msg);
    }

    /**
     * Kirim email notifikasi ke admin bahwa password telah diganti.
     * Subject & body disesuaikan dengan konteks "admin password change",
     * dan menyertakan password plain (baru di-hash di DB) untuk referensi
     * admin agar tidak lupa.
     */
    private function sendPasswordChangeEmail(string $to, string $name, string $newPassword): array
    {
        $subject = 'Password Admin Berhasil Diubah - Sistem Absensi Kampus';
        $body    = "Halo {$name},\n\n"
                 . "Password akun admin Anda baru saja berhasil diubah.\n\n"
                 . "Password baru Anda: {$newPassword}\n\n"
                 . "Simpan email ini sebagai catatan pribadi. Demi keamanan, "
                 . "segera hapus setelah Anda mencatat password di tempat aman.\n\n"
                 . "Jika Anda tidak merasa melakukan perubahan ini, "
                 . "segera hubungi tim teknis — kemungkinan akun Anda telah disalahgunakan.\n\n"
                 . "--\nSistem Absensi Kampus";

        if (! function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Ekstensi cURL tidak tersedia di server.'];
        }

        return $this->dispatchMail($to, $subject, $body);
    }

    /**
     * Generic email dispatcher: coba Gmail (webriau.com) dulu, fallback
     * ke Turbo-SMTP jika gagal. Mengikuti pola yang sama dengan
     * Auth::sendOtpEmail() / Auth::sendPasswordResetEmail().
     */
    private function dispatchMail(string $to, string $subject, string $body): array
    {
        $gmail = $this->sendViaGmail($to, $subject, $body);
        if ($gmail['ok']) {
            return $gmail;
        }
        return $this->sendViaTurboSmtp($to, $subject, $body);
    }

    private function sendViaGmail(string $to, string $subject, string $body): array
    {
        $url    = (string) env('otp.mailApiUrl', 'https://gmail.webriau.com/servera.php');
        $apiKey = (string) env('otp.mailApiKey', 'b5d4c11bd3854a159b1ac9b417eec952d726eb0e037099f46925e3ea381b0128');

        $payload = http_build_query([
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-Key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'message' => 'Kesalahan jaringan: ' . $curlError];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'message' => 'Server email menolak permintaan (HTTP ' . $httpCode . ').'];
        }
        return ['ok' => true, 'message' => 'OK'];
    }

    private function sendViaTurboSmtp(string $to, string $subject, string $body): array
    {
        $url            = 'https://api.turbo-smtp.com/api/v2/mail/send';
        $consumerKey    = (string) env('turboSmtp.consumerKey', 'eda1805b1b910db73358');
        $consumerSecret = (string) env('turboSmtp.consumerSecret', 'h31XlvNIOA7txmCUnPGr');

        $data = [
            'from'         => 'noreply@kursuscerdas.com',
            'to'           => $to,
            'subject'      => $subject,
            'content'      => $body,
            'html_content' => '<p>' . nl2br(esc($body)) . '</p>',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'consumerKey: ' . $consumerKey,
            'consumerSecret: ' . $consumerSecret,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'message' => 'TurboSMTP: ' . $curlError];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'message' => 'TurboSMTP HTTP ' . $httpCode];
        }
        return ['ok' => true, 'message' => 'OK'];
    }
}
