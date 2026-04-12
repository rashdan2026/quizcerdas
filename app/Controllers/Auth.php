<?php

namespace App\Controllers;

use App\Models\LecturerModel;
use App\Models\OtpCodeModel;
use App\Models\StudentModel;

class Auth extends BaseController
{
    public function index()
    {
        if (session('logged_in')) {
            return session('role') === 'lecturer'
                ? redirect()->to('/lecturer/subjects')
                : redirect()->to('/student/dashboard');
        }

        $captcha = $this->generateCaptcha();

        return view('auth/login', ['captcha' => $captcha]);
    }

    public function login()
    {
        $role       = $this->request->getPost('role');
        $identifier = trim((string) $this->request->getPost('identifier'));
        $password   = (string) $this->request->getPost('password');
        $captcha    = strtoupper(trim((string) $this->request->getPost('captcha')));
        $session    = session();

        if (! in_array($role, ['student', 'lecturer'], true)) {
            return redirect()->back()->withInput()->with('error', 'Role login tidak valid.');
        }

        if ($captcha !== $session->get('captcha_code')) {
            $this->generateCaptcha();

            return redirect()->back()->withInput()->with('error', 'Captcha tidak sesuai.');
        }

        if ($role === 'student') {
            $studentModel = new StudentModel();
            $student      = $studentModel->where('email', $identifier)->first();

            $validPassword = false;
            if ($student) {
                $storedPassword = (string) $student['password'];
                $validPassword  = str_starts_with($storedPassword, '$2')
                    ? password_verify($password, $storedPassword)
                    : hash_equals($storedPassword, $password);
            }

            if (! $student) {
                $this->generateCaptcha();

                return redirect()->back()->withInput()->with('error', 'Data Mahasiswa tidak ditemukan!');
            }

            if (! $validPassword) {
                $this->generateCaptcha();

                return redirect()->back()->withInput()->with('error', 'Password salah!');
            }

            if (empty($student['last_login'])) {
                $session->set('password_change_email', $student['email']);

                return redirect()->to('/auth/change-password')
                    ->with('info', 'Login pertama terdeteksi. Silakan ganti password terlebih dahulu.');
            }

            // Increment login count
            $loginCount = ((int) ($student['login_count'] ?? 0)) + 1;
            $studentModel->update($student['email'], ['login_count' => $loginCount]);

            // OTP diperlukan setiap 5 kali login
            $requireOtp = ($loginCount % 5 === 0);

            if (! $requireOtp) {
                // Langsung login tanpa OTP
                $session->set([
                    'logged_in' => true,
                    'role'      => 'student',
                    'user_id'   => $student['email'],
                    'user_name' => $student['nama'],
                ]);

                return redirect()->to('/student/dashboard')
                    ->with('success', 'Login mahasiswa berhasil.');
            }

            // Setiap 5 kali login, kirim OTP
            $otp = $this->issueOtp($student['email']);
            $mailResult = $this->sendOtpEmail($student['email'], $student['nama'], $otp['code']);

            if (! $mailResult['ok']) {
                (new OtpCodeModel())->delete($otp['id']);

                // Fallback: dalam development mode, tetap izinkan OTP dengan menampilkan kode
                if (ENVIRONMENT === 'development') {
                    $session->set([
                        'pending_student_email' => $student['email'],
                        'dev_otp_code'        => $otp['code'],
                        'otp_email'           => $student['email'],
                    ]);

                    return redirect()->to('/auth/otp')
                        ->with('warning', 'Gagal mengirim OTP ke email (mode development). Kode OTP: ' . $otp['code']);
                }

                $this->generateCaptcha();

                return redirect()->back()->withInput()->with(
                    'error',
                    'OTP gagal dikirim ke email. ' . $mailResult['message']
                );
            }

            $session->set('pending_student_email', $student['email']);
            $session->set('otp_email', $student['email']);

            return redirect()->to('/auth/otp')->with('info', 'OTP telah dikirim ke email Anda.');
        }

        $lecturerModel = new LecturerModel();
        $lecturer      = $lecturerModel->where('email', $identifier)->first();

        if (! $lecturer || ! password_verify($password, $lecturer['password'])) {
            $this->generateCaptcha();

            return redirect()->back()->withInput()->with('error', 'Kredensial dosen tidak valid.');
        }

        $lecturerModel->update($lecturer['id'], ['last_login' => date('Y-m-d H:i:s')]);
        $session->set([
            'logged_in' => true,
            'role'      => 'lecturer',
            'user_id'   => $lecturer['id'],
            'user_name' => $lecturer['nama'],
        ]);

        return redirect()->to('/lecturer/subjects')->with('success', 'Login dosen berhasil.');
    }

    public function otp()
    {
        $session = session();
        $email   = $session->get('pending_student_email');

        if (! $email) {
            return redirect()->to('/auth')->with('error', 'Sesi OTP tidak ditemukan.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('auth/otp');
        }

        $otpInput = trim((string) $this->request->getPost('otp_code'));
        if (! preg_match('/^\d{6}$/', $otpInput)) {
            return redirect()->back()->withInput()->with('error', 'Kode OTP harus 6 digit angka.');
        }

        $otpModel = new OtpCodeModel();
        $otpRow   = $otpModel
            ->where('email', $email)
            ->where('otp_code', $otpInput)
            ->where('is_used', 0)
            ->where('expired_at >=', date('Y-m-d H:i:s'))
            ->orderBy('id', 'DESC')
            ->first();

        if (! $otpRow) {
            return redirect()->back()->withInput()->with('error', 'OTP tidak valid atau sudah kedaluwarsa.');
        }

        $otpModel->update($otpRow['id'], ['is_used' => 1]);

        $studentModel = new StudentModel();
        $student      = $studentModel->find($email);
        $studentModel->update($email, [
            'last_login'  => date('Y-m-d H:i:s'),
            'login_count' => 0, // Reset login count setelah OTP berhasil
        ]);

        $session->remove('pending_student_email');
        $session->remove('dev_otp_code');
        $session->set([
            'logged_in' => true,
            'role'      => 'student',
            'user_id'   => $student['email'],
            'user_name' => $student['nama'],
        ]);

        return redirect()->to('/student/dashboard')->with('success', 'Login mahasiswa berhasil.');
    }

    public function changePassword()
    {
        $session = session();
        $email   = $session->get('password_change_email');

        if (! $email) {
            return redirect()->to('/auth')->with('error', 'Sesi ubah password tidak ditemukan.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('auth/change_password');
        }

        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if (strlen($newPassword) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password baru minimal 8 karakter.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
        }

        $studentModel = new StudentModel();
        $studentModel->update($email, [
            'password'   => password_hash($newPassword, PASSWORD_BCRYPT),
            'last_login' => date('Y-m-d H:i:s'),
        ]);

        $session->remove('password_change_email');

        return redirect()->to('/auth')->with('success', 'Password berhasil diperbarui. Silakan login ulang.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/auth')->with('success', 'Logout berhasil.');
    }

    private function issueOtp(string $email): array
    {
        $otpCode = (string) random_int(100000, 999999);

        $otpModel = new OtpCodeModel();
        $otpModel->insert([
            'email'      => $email,
            'otp_code'   => $otpCode,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
            'is_used'    => 0,
        ]);

        return [
            'id'   => (int) $otpModel->getInsertID(),
            'code' => $otpCode,
        ];
    }

    private function sendOtpEmail(string $to, string $name, string $otpCode): array
    {
        if (! function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Ekstensi cURL tidak tersedia di server.'];
        }

        // Try Gmail (webriau.com) first
        $gmailResult = $this->sendViaGmail($to, $name, $otpCode);
        
        if ($gmailResult['ok']) {
            return $gmailResult;
        }

        // If Gmail fails, fallback to Turbo-SMTP
        return $this->sendViaTurboSmtp($to, $name, $otpCode);
    }

    private function sendViaGmail(string $to, string $name, string $otpCode): array
    {
        $serverAUrl = (string) env('otp.mailApiUrl', 'https://gmail.webriau.com/servera.php');
        $apiKey     = (string) env('otp.mailApiKey', 'b5d4c11bd3854a159b1ac9b417eec952d726eb0e037099f46925e3ea381b0128');

        $payload = [
            'to'      => $to,
            'subject' => 'Kode OTP Login Absensi Mahasiswa',
            'body'    => "Halo {$name},\n\nKode OTP Anda: {$otpCode}\nBerlaku 5 menit.\n\nJangan bagikan kode ini ke siapa pun.",
        ];

        $ch = curl_init($serverAUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
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
            $responseData = json_decode((string) $response, true);
            $errorText    = is_array($responseData) && isset($responseData['message'])
                ? (string) $responseData['message']
                : 'HTTP ' . $httpCode;

            return ['ok' => false, 'message' => 'Server email menolak permintaan (' . $errorText . ').'];
        }

        return ['ok' => true, 'message' => 'OK'];
    }

    private function sendViaTurboSmtp(string $to, string $name, string $otpCode): array
    {
        $url = 'https://api.turbo-smtp.com/api/v2/mail/send';
        $consumerKey = (string) env('turboSmtp.consumerKey', 'eda1805b1b910db73358');
        $consumerSecret = (string) env('turboSmtp.consumerSecret', 'h31XlvNIOA7txmCUnPGr');

        $data = [
            'from' => 'noreply@kursuscerdas.com',
            'to' => $to,
            'subject' => 'Kode OTP Login Absensi Mahasiswa',
            'content' => "Halo {$name},\n\nKode OTP Anda: {$otpCode}\nBerlaku 5 menit.\n\nJangan bagikan kode ini ke siapa pun.",
            'html_content' => "<p>Halo {$name},</p><p>Kode OTP Anda: <strong>{$otpCode}</strong></p><p>Berlaku 5 menit.</p><p>Jangan bagikan kode ini ke siapa pun.</p>",
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

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'message' => 'Turbo-SMTP error: ' . $curlError];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'message' => 'Turbo-SMTP gagal mengirim email (HTTP ' . $httpCode . ')'];
        }

        return ['ok' => true, 'message' => 'OK (via Turbo-SMTP)'];
    }

    private function generateCaptcha(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code  = substr(str_shuffle($chars), 0, 5);

        session()->set('captcha_code', strtoupper($code));

        return $code;
    }
}
