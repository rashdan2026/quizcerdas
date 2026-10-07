<?php

namespace App\Controllers;

use App\Models\OtpCodeModel;
use App\Models\StudentModel;

class Registration extends BaseController
{
    private const MAX_ATTEMPTS_PER_DAY = 3;
    private const OTP_VALIDITY_MINUTES = 15;

    public function __construct()
    {
        helper('cookie');
    }

    public function index()
    {
        $data = $this->getAttemptInfo();
        return view('auth/register', $data);
    }

    public function register()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/register');
        }

        if (!$this->checkAttemptLimit()) {
            return redirect()->back()->withInput()->with('error', 'Anda telah mencapai batas maksimal 3x pendaftaran per hari. Silakan coba lagi besok.');
        }

        $npm     = trim((string) $this->request->getPost('npm'));
        $email   = trim((string) $this->request->getPost('email'));
        $nama    = trim((string) $this->request->getPost('nama'));
        $kelas   = trim((string) $this->request->getPost('kelas'));
        $noWa    = trim((string) $this->request->getPost('no_whatsapp'));
        $password = (string) $this->request->getPost('password');
        $passwd  = (string) $this->request->getPost('passwd');

        if (strlen($npm) !== 9 || !ctype_digit($npm)) {
            return redirect()->back()->withInput()->with('error', 'NPM harus 9 digit angka.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Format email tidak valid.');
        }

        if (strlen($nama) < 3 || strlen($nama) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama harus 3-100 karakter.');
        }

        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password minimal 8 karakter.');
        }

        if (strlen($kelas) !== 1) {
            return redirect()->back()->withInput()->with('error', 'Kelas wajib diisi 1 karakter.');
        }

        if ($password !== $passwd) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
        }

        $studentModel = new StudentModel();

        if ($studentModel->find($email)) {
            return redirect()->back()->withInput()->with('error', 'Email sudah terdaftar.');
        }

        if ($studentModel->find($npm)) {
            return redirect()->back()->withInput()->with('error', 'NPM sudah terdaftar.');
        }

        $pendingEmail = session()->get('pending_reg_email');
        if ($pendingEmail && $pendingEmail === $email) {
            return redirect()->to('/register/verify-email')->withInput();
        }

        $otpCode = (string) random_int(100000, 999999);
        $otpModel = new OtpCodeModel();

        $otpModel->where('email', $email)->delete();

        $otpModel->insert([
            'email'      => $email,
            'otp_code'   => $otpCode,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+' . self::OTP_VALIDITY_MINUTES . ' minutes')),
            'is_used'    => 0,
        ]);

        $mailResult = $this->sendVerificationEmail($email, $nama, $otpCode);

        if (!$mailResult['ok']) {
            if (ENVIRONMENT === 'development') {
                session()->set([
                    'pending_reg_email' => $email,
                    'pending_reg_data'  => [
                        'npm' => $npm,
                        'email' => $email,
                        'nama' => $nama,
                        'kelas' => $kelas,
                        'no_whatsapp' => $noWa,
                        'password' => password_hash($password, PASSWORD_BCRYPT),
                    ],
                    'dev_reg_otp' => $otpCode,
                ]);

                return redirect()->to('/register/verify-email')
                    ->with('warning', 'Gagal mengirim email (mode development). Kode OTP: ' . $otpCode);
            }

            return redirect()->back()->withInput()->with('error', 'Gagal mengirim email verifikasi: ' . $mailResult['message']);
        }

        $this->incrementAttempt();

        session()->set([
            'pending_reg_email' => $email,
            'pending_reg_data'  => [
                'npm' => $npm,
                'email' => $email,
                'nama' => $nama,
                'kelas' => $kelas,
                'no_whatsapp' => $noWa,
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ],
        ]);

        return redirect()->to('/register/verify-email')->with('info', 'Kode verifikasi telah dikirim ke email Anda.');
    }

    public function verifyEmail()
    {
        $pendingEmail = session()->get('pending_reg_email');
        if (!$pendingEmail) {
            return redirect()->to('/register')->with('error', 'Sesi verifikasi tidak ditemukan.');
        }

        $otpModel = new OtpCodeModel();
        $otpRow = $otpModel->where('email', $pendingEmail)->where('is_used', 0)->orderBy('id', 'DESC')->first();

        $data = [
            'email' => $pendingEmail,
            'otp_valid' => $otpRow && strtotime($otpRow['expired_at']) > time(),
            'expired_at' => $otpRow ? $otpRow['expired_at'] : null,
            'dev_reg_otp' => ENVIRONMENT === 'development' ? session()->get('dev_reg_otp') : null,
        ];

        return view('auth/register_verify', $data);
    }

    public function confirmVerify()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/register/verify-email');
        }

        $pendingEmail = session()->get('pending_reg_email');
        $pendingData = session()->get('pending_reg_data');

        if (!$pendingEmail || !$pendingData) {
            return redirect()->to('/register')->with('error', 'Sesi pendaftaran tidak ditemukan.');
        }

        $otpInput = trim((string) $this->request->getPost('otp_code'));
        if (!preg_match('/^\d{6}$/', $otpInput)) {
            return redirect()->back()->withInput()->with('error', 'Kode OTP harus 6 digit angka.');
        }

        $otpModel = new OtpCodeModel();
        $otpRow = $otpModel
            ->where('email', $pendingEmail)
            ->where('otp_code', $otpInput)
            ->where('is_used', 0)
            ->where('expired_at >=', date('Y-m-d H:i:s'))
            ->first();

        if (!$otpRow) {
            return redirect()->back()->withInput()->with('error', 'Kode OTP tidak valid atau sudah kedaluwarsa.');
        }

        $otpModel->update($otpRow['id'], ['is_used' => 1]);

        $studentModel = new StudentModel();
        $studentModel->insert([
            'npm'     => $pendingData['npm'],
            'email'   => $pendingData['email'],
            'nama'    => $pendingData['nama'],
            'kelas'   => $pendingData['kelas'] ?: null,
            'no_whatsapp' => $pendingData['no_whatsapp'] ?: '',
            'password' => $pendingData['password'],
        ]);

        session()->remove('pending_reg_email');
        session()->remove('pending_reg_data');
        session()->remove('dev_reg_otp');

        return redirect()->to('/auth')->with('success', 'Pendaftaran berhasil! Silakan login dengan akun Anda.');
    }

    public function resendOtp()
    {
        $pendingEmail = session()->get('pending_reg_email');
        $pendingData = session()->get('pending_reg_data');

        if (!$pendingEmail || !$pendingData) {
            return redirect()->to('/register')->with('error', 'Sesi pendaftaran tidak ditemukan.');
        }

        $otpModel = new OtpCodeModel();
        $otpModel->where('email', $pendingEmail)->delete();

        $otpCode = (string) random_int(100000, 999999);
        $otpModel->insert([
            'email'      => $pendingEmail,
            'otp_code'   => $otpCode,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+' . self::OTP_VALIDITY_MINUTES . ' minutes')),
            'is_used'    => 0,
        ]);

        $mailResult = $this->sendVerificationEmail($pendingEmail, $pendingData['nama'], $otpCode);

        if (!$mailResult['ok']) {
            if (ENVIRONMENT === 'development') {
                session()->set('dev_reg_otp', $otpCode);
                return redirect()->back()->with('warning', 'Gagal mengirim email (mode development). Kode OTP: ' . $otpCode);
            }
            return redirect()->back()->with('error', 'Gagal mengirim email: ' . $mailResult['message']);
        }

        return redirect()->back()->with('success', 'Kode verifikasi telah dikirim ulang ke email.');
    }

    private function sendVerificationEmail(string $to, string $name, string $otpCode): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Ekstensi cURL tidak tersedia di server.'];
        }

        $gmailResult = $this->sendViaGmail($to, $name, $otpCode);
        if ($gmailResult['ok']) {
            return $gmailResult;
        }

        return $this->sendViaTurboSmtp($to, $name, $otpCode);
    }

    private function sendViaGmail(string $to, string $name, string $otpCode): array
    {
        $serverAUrl = (string) env('otp.mailApiUrl', 'https://gmail.webriau.com/servera.php');
        $apiKey = (string) env('otp.mailApiKey', 'b5d4c11bd3854a159b1ac9b417eec952d726eb0e037099f46925e3ea381b0128');

        $payload = [
            'to'      => $to,
            'subject' => 'Kode Verifikasi Pendaftaran - Sistem Absensi Kampus',
            'body'    => "Halo {$name},\n\nTerima kasih telah mendaftar.\n\nKode verifikasi Anda: {$otpCode}\nBerlaku selama " . self::OTP_VALIDITY_MINUTES . " menit.\n\nJangan bagikan kode ini ke siapa pun.\n\nSalam,\nAdmin Sistem Absensi Kampus",
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

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'message' => 'Kesalahan jaringan: ' . $curlError];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $responseData = json_decode((string) $response, true);
            $errorText = is_array($responseData) && isset($responseData['message'])
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
            'subject' => 'Kode Verifikasi Pendaftaran - Sistem Absensi Kampus',
            'content' => "Halo {$name},\n\nTerima kasih telah mendaftar.\n\nKode verifikasi Anda: {$otpCode}\nBerlaku selama " . self::OTP_VALIDITY_MINUTES . " menit.\n\nJangan bagikan kode ini ke siapa pun.\n\nSalam,\nAdmin Sistem Absensi Kampus",
            'html_content' => "<p>Halo {$name},</p><p>Terima kasih telah mendaftar.</p><p>Kode verifikasi Anda: <strong>{$otpCode}</strong></p><p>Berlaku selama " . self::OTP_VALIDITY_MINUTES . " menit.</p><p>Jangan bagikan kode ini ke siapa pun.</p><p>Salam,<br>Admin Sistem Absensi Kampus</p>",
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

    private function getAttemptInfo(): array
    {
        $today = date('Y-m-d');
        $cookie = get_cookie('reg_attempts');

        if ($cookie) {
            $data = json_decode($cookie, true);
            if (is_array($data) && ($data['date'] ?? '') === $today) {
                return [
                    'attempts_today' => (int) ($data['count'] ?? 0),
                    'max_attempts' => self::MAX_ATTEMPTS_PER_DAY,
                    'can_register' => ($data['count'] ?? 0) < self::MAX_ATTEMPTS_PER_DAY,
                ];
            }
        }

        return [
            'attempts_today' => 0,
            'max_attempts' => self::MAX_ATTEMPTS_PER_DAY,
            'can_register' => true,
        ];
    }

    private function checkAttemptLimit(): bool
    {
        $info = $this->getAttemptInfo();
        return $info['can_register'];
    }

    private function incrementAttempt(): void
    {
        $today = date('Y-m-d');
        $cookie = get_cookie('reg_attempts');

        $data = ['date' => $today, 'count' => 0];
        if ($cookie) {
            $decoded = json_decode($cookie, true);
            if (is_array($decoded) && ($decoded['date'] ?? '') === $today) {
                $data['count'] = (int) ($decoded['count'] ?? 0);
            }
        }

        $data['count']++;

        $expire = strtotime('tomorrow') - time();
        set_cookie('reg_attempts', json_encode($data), $expire);
    }
}
