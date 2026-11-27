<?php

namespace App\Controllers;

use App\Models\LecturerModel;
use App\Models\LecturerRegistrationModel;

/**
 * Registrasi mandiri dosen KelasMerdeka.com.
 *
 * Alur: formulir -> kode verifikasi ke email institusi (.ac.id) ->
 * verifikasi -> akun dosen aktif (tanpa approval admin),
 * dengan konfirmasi profil pada first login (Lecturer\FirstLogin).
 */
class DosenRegistration extends BaseController
{
    // Konfigurasi keamanan kode verifikasi (satu sumber, jangan disebar).
    private const CODE_TTL_MINUTES   = 15;  // masa berlaku kode verifikasi
    private const MAX_VERIFY_ATTEMPTS = 5;  // batas percobaan verifikasi per pendaftaran
    private const RESEND_COOLDOWN_SEC = 60; // jeda minimal pengiriman ulang
    private const PENDING_TTL_HOURS   = 24; // usia maksimum data pending (dibersihkan otomatis)

    public function __construct()
    {
        helper('cookie');
    }

    public function index()
    {
        return view('auth/register_dosen');
    }

    public function register()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/register/dosen');
        }

        $nama          = trim((string) $this->request->getPost('nama'));
        $jenisKelamin  = trim((string) $this->request->getPost('jenis_kelamin'));
        $tanggalLahir  = trim((string) $this->request->getPost('tanggal_lahir'));
        $namaKampus    = trim((string) $this->request->getPost('nama_kampus'));
        $email         = strtolower(trim((string) $this->request->getPost('email')));
        $password      = (string) $this->request->getPost('password');
        $passwd        = (string) $this->request->getPost('passwd');

        if ($nama === '' || mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama lengkap wajib diisi (3-100 karakter).');
        }

        if (! in_array($jenisKelamin, ['Laki-Laki', 'Perempuan'], true)) {
            return redirect()->back()->withInput()->with('error', 'Jenis kelamin wajib dipilih.');
        }

        if ($tanggalLahir === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalLahir)) {
            return redirect()->back()->withInput()->with('error', 'Tanggal lahir wajib diisi dengan format yang valid.');
        }

        [$ok, $errMsg] = $this->validateBirthDate($tanggalLahir);
        if (! $ok) {
            return redirect()->back()->withInput()->with('error', $errMsg);
        }

        if ($namaKampus === '' || mb_strlen($namaKampus) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama kampus wajib diisi (maksimal 100 karakter).');
        }

        if (strlen($email) > 100 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $this->isAcIdEmail($email)) {
            return redirect()->back()->withInput()->with('error', 'Email institusi wajib berakhiran .ac.id (contoh: dosen@universitas.ac.id).');
        }

        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password minimal 8 karakter.');
        }

        if ($password !== $passwd) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
        }

        $lecturerModel = new LecturerModel();
        $regModel      = new LecturerRegistrationModel();

        // Cegah email duplikat (akun dosen aktif maupun pendaftaran pending).
        if ($lecturerModel->where('email', $email)->first()) {
            return redirect()->back()->withInput()->with('error', 'Email sudah terdaftar sebagai dosen. Silakan login.');
        }

        $pending = $regModel->where('email', $email)->first();
        if ($pending && strtotime($pending['created_at']) > strtotime('-' . self::PENDING_TTL_HOURS . ' hours')) {
            // Pendaftaran dengan email ini masih menunggu verifikasi.
            session()->set('pending_dosen_email', $email);
            return redirect()->to('/register/dosen/verify-email')
                ->with('info', 'Email ini sudah memiliki proses verifikasi yang berjalan. Silakan masukkan kode verifikasi.');
        }

        // Bersihkan data pending kedaluwarsa (kebijakan pembersihan otomatis).
        $regModel->where('created_at <', date('Y-m-d H:i:s', strtotime('-' . self::PENDING_TTL_HOURS . ' hours')))->delete();

        $code = $this->issueVerificationCode($regModel, $email);

        $regData = [
            'nama'          => $nama,
            'email'         => $email,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'nama_kampus'   => $namaKampus,
            'password'      => password_hash($password, PASSWORD_BCRYPT),
            'verify_code_hash'   => password_hash($code, PASSWORD_BCRYPT),
            'verify_expires_at'  => date('Y-m-d H:i:s', strtotime('+' . self::CODE_TTL_MINUTES . ' minutes')),
            'verify_attempts'    => 0,
            'resend_available_at' => date('Y-m-d H:i:s', time() + self::RESEND_COOLDOWN_SEC),
        ];

        if ($pending) {
            $regModel->update($pending['id'], $regData);
        } else {
            $regModel->insert($regData);
        }

        $mailResult = $this->sendVerificationEmail($email, $nama, $code);

        if (! $mailResult['ok']) {
            // Email gagal terkirim: JANGAN anggap terverifikasi.
            if (ENVIRONMENT === 'development') {
                session()->set(['pending_dosen_email' => $email, 'dev_dosen_otp' => $code]);
                return redirect()->to('/register/dosen/verify-email')
                    ->with('warning', 'Gagal mengirim email (mode development). Kode verifikasi: ' . $code);
            }

            return redirect()->back()->withInput()
                ->with('error', 'Gagal mengirim email verifikasi: ' . $mailResult['message']);
        }

        session()->set('pending_dosen_email', $email);

        return redirect()->to('/register/dosen/verify-email')
            ->with('info', 'Kode verifikasi telah dikirim ke email institusi Anda.');
    }

    public function verifyEmail()
    {
        $email = session()->get('pending_dosen_email');
        if (! $email) {
            return redirect()->to('/register/dosen')->with('error', 'Sesi verifikasi tidak ditemukan.');
        }

        $pending = (new LecturerRegistrationModel())->where('email', $email)->first();

        $data = [
            'email'          => $email,
            'code_valid'     => $pending && strtotime($pending['verify_expires_at']) > time(),
            'expired_at'     => $pending ? $pending['verify_expires_at'] : null,
            'resend_at'      => $pending ? $pending['resend_available_at'] : null,
            'attempts_left'  => $pending ? max(0, self::MAX_VERIFY_ATTEMPTS - (int) $pending['verify_attempts']) : 0,
            'dev_dosen_otp'  => ENVIRONMENT === 'development' ? session()->get('dev_dosen_otp') : null,
        ];

        return view('auth/register_dosen_verify', $data);
    }

    public function confirmVerify()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/register/dosen/verify-email');
        }

        $email = session()->get('pending_dosen_email');
        if (! $email) {
            return redirect()->to('/register/dosen')->with('error', 'Sesi pendaftaran tidak ditemukan.');
        }

        $regModel = new LecturerRegistrationModel();
        $pending  = $regModel->where('email', $email)->first();

        if (! $pending) {
            session()->remove('pending_dosen_email');
            return redirect()->to('/register/dosen')->with('error', 'Data pendaftaran tidak ditemukan. Silakan daftar ulang.');
        }

        // Batas percobaan verifikasi tercapai.
        if ((int) $pending['verify_attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            return redirect()->back()->with('error', 'Batas percobaan verifikasi tercapai. Silakan minta kode baru atau daftar ulang besok.');
        }

        $codeInput = trim((string) $this->request->getPost('verify_code'));
        if (! preg_match('/^\d{6}$/', $codeInput)) {
            return redirect()->back()->withInput()->with('error', 'Kode verifikasi harus 6 digit angka.');
        }

        $expired  = strtotime($pending['verify_expires_at']) <= time();
        $verified = password_verify($codeInput, (string) $pending['verify_code_hash']);

        if ($expired || ! $verified) {
            // Counter percobaan hanya dinaikkan untuk kode yang salah dalam masa berlaku.
            if (! $expired) {
                $regModel->update($pending['id'], ['verify_attempts' => (int) $pending['verify_attempts'] + 1]);
            }

            $message = $expired
                ? 'Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru.'
                : 'Kode verifikasi tidak valid. Percobaan tersisa: '
                    . max(0, self::MAX_VERIFY_ATTEMPTS - ((int) $pending['verify_attempts'] + 1)) . '.';

            return redirect()->back()->withInput()->with('error', $message);
        }

        // Kode benar & berlaku: aktifkan akun dosen tanpa approval admin.
        $lecturerModel = new LecturerModel();

        // Cek ulang uniqueness (kondisi race saat proses pending).
        if ($lecturerModel->where('email', $email)->first()) {
            $regModel->delete($pending['id']);
            session()->remove(['pending_dosen_email', 'dev_dosen_otp']);
            return redirect()->to('/auth')->with('error', 'Email sudah terdaftar sebagai dosen. Silakan login.');
        }

        $lecturerModel->insert([
            'nama'          => $pending['nama'],
            'email'         => $pending['email'],
            'jenis_kelamin' => $pending['jenis_kelamin'],
            'tanggal_lahir' => $pending['tanggal_lahir'],
            'nama_kampus'   => $pending['nama_kampus'],
            'password'      => $pending['password'],
            'is_active'     => 1,
            'email_verified'    => 1,
            'profile_confirmed' => 0, // first login: wajib konfirmasi profil
        ]);

        // Kode hanya dipakai sekali: hapus data pending (kode tidak berlaku lagi).
        $regModel->delete($pending['id']);
        session()->remove(['pending_dosen_email', 'dev_dosen_otp']);

        return redirect()->to('/auth')
            ->with('success', 'Verifikasi berhasil! Akun dosen Anda aktif. Silakan login untuk mengonfirmasi profil.');
    }

    public function resendCode()
    {
        $email = session()->get('pending_dosen_email');
        if (! $email) {
            return redirect()->to('/register/dosen')->with('error', 'Sesi verifikasi tidak ditemukan.');
        }

        $regModel = new LecturerRegistrationModel();
        $pending  = $regModel->where('email', $email)->first();

        if (! $pending) {
            return redirect()->to('/register/dosen')->with('error', 'Data pendaftaran tidak ditemukan. Silakan daftar ulang.');
        }

        // Rate limiting pengiriman ulang.
        if ($pending['resend_available_at'] && strtotime($pending['resend_available_at']) > time()) {
            $wait = strtotime($pending['resend_available_at']) - time();
            return redirect()->back()->with('error', 'Tunggu ' . $wait . ' detik sebelum meminta kode baru.');
        }

        // Kode baru acak (cryptographically secure). Baris pending TIDAK
        // dihapus di sini — hanya diganti kode hash-nya (kode lama hangus).
        $code = (string) random_int(100000, 999999);

        // Kode baru menggantikan kode lama (hash diganti => kode lama tidak berlaku).
        $regModel->update($pending['id'], [
            'verify_code_hash'    => password_hash($code, PASSWORD_BCRYPT),
            'verify_expires_at'   => date('Y-m-d H:i:s', strtotime('+' . self::CODE_TTL_MINUTES . ' minutes')),
            'verify_attempts'     => 0,
            'resend_available_at' => date('Y-m-d H:i:s', time() + self::RESEND_COOLDOWN_SEC),
        ]);

        $mailResult = $this->sendVerificationEmail($email, $pending['nama'], $code);

        if (! $mailResult['ok']) {
            if (ENVIRONMENT === 'development') {
                session()->set('dev_dosen_otp', $code);
                return redirect()->back()->with('warning', 'Gagal mengirim email (mode development). Kode verifikasi: ' . $code);
            }
            return redirect()->back()->with('error', 'Gagal mengirim email: ' . $mailResult['message']);
        }

        return redirect()->back()->with('success', 'Kode verifikasi baru telah dikirim ke email Anda.');
    }

    /**
     * Validasi email institusi: harus alamat email valid dan domain
     * benar-benar berakhiran ".ac.id" (subdomain tetap diperbolehkan).
     * "user@acid.com" atau "user@ac.id.evil.com" ditolak.
     */
    private function isAcIdEmail(string $email): bool
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];
        return (bool) preg_match('/^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.ac\.id$/', $domain);
    }

    private function validateBirthDate(string $date): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date));

        if (! checkdate($m, $d, $y)) {
            return [false, 'Tanggal lahir tidak valid.'];
        }

        if (strtotime($date) > time()) {
            return [false, 'Tanggal lahir tidak boleh di masa depan.'];
        }

        $minYear = (int) date('Y') - 100;
        if ($y < $minYear) {
            return [false, 'Tanggal lahir tidak wajar (maksimal usia 100 tahun).'];
        }

        return [true, ''];
    }

    private function issueVerificationCode(LecturerRegistrationModel $regModel, string $email): string
    {
        $regModel->where('email', $email)->delete(); // satu baris pending per email

        // random_int = cryptographically secure random generator.
        return (string) random_int(100000, 999999);
    }

    // ==================== Pengiriman email ====================
    // Menggunakan kanal email yang sama dengan aplikasi
    // (Gmail webriau.com, fallback Turbo-SMTP) via env configuration.

    private function sendVerificationEmail(string $to, string $name, string $code): array
    {
        if (! function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Ekstensi cURL tidak tersedia di server.'];
        }

        $subject = 'Kode Verifikasi Pendaftaran Dosen - ' . app_name();
        $html = $this->buildVerificationEmailHtml($name, $code);
        $text = "Halo {$name},\n\n"
            . "Kode verifikasi pendaftaran dosen " . app_name() . ": {$code}\n\n"
            . "Kode berlaku " . self::CODE_TTL_MINUTES . " menit.\n"
            . "Jangan berikan kode ini kepada siapa pun.\n\n"
            . "Salam,\nAdmin " . app_name();

        $gmailResult = $this->sendViaGmail($to, $subject, $html, $text);
        if ($gmailResult['ok']) {
            return $gmailResult;
        }

        return $this->sendViaTurboSmtp($to, $subject, $html, $text);
    }

    private function buildVerificationEmailHtml(string $name, string $code): string
    {
        $appName = app_name();

        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:520px;margin:0 auto;padding:24px;">'
            . '<div style="background:#4F46E5;border-radius:12px 12px 0 0;padding:24px;text-align:center;">'
            . '<h2 style="color:#FFFFFF;margin:0;font-size:20px;">' . esc($appName) . '</h2>'
            . '<p style="color:#C7D2FE;margin:8px 0 0;font-size:13px;">Verifikasi Email Pendaftaran Dosen</p>'
            . '</div>'
            . '<div style="background:#FFFFFF;border-radius:0 0 12px 12px;padding:32px 28px;">'
            . '<p style="color:#1E293B;font-size:15px;margin:0 0 12px;">Halo <strong>' . esc($name) . '</strong>,</p>'
            . '<p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 20px;">'
            . 'Gunakan kode berikut untuk memverifikasi email institusi Anda pada pendaftaran akun dosen:</p>'
            . '<div style="text-align:center;margin:0 0 20px;">'
            . '<span style="display:inline-block;background:#EEF2FF;border:2px dashed #4F46E5;color:#3730A3;'
            . 'font-size:32px;font-weight:bold;letter-spacing:10px;padding:14px 28px;border-radius:10px;">' . esc($code) . '</span>'
            . '</div>'
            . '<p style="color:#475569;font-size:14px;margin:0 0 8px;">Kode berlaku selama <strong>' . self::CODE_TTL_MINUTES . ' menit</strong>.</p>'
            . '<p style="color:#B91C1C;font-size:13px;margin:0 0 20px;"><strong>Peringatan:</strong> Jangan berikan kode ini kepada siapa pun, termasuk pihak yang mengaku admin.</p>'
            . '<p style="color:#94A3B8;font-size:12px;margin:0;">Jika Anda tidak merasa mendaftar, abaikan email ini.</p>'
            . '</div></div></body></html>';
    }

    private function sendViaGmail(string $to, string $subject, string $html, string $text): array
    {
        $serverAUrl = (string) env('otp.mailApiUrl', 'https://gmail.webriau.com/servera.php');
        $apiKey     = (string) env('otp.mailApiKey');

        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Konfigurasi email (otp.mailApiKey) belum diatur.'];
        }

        $payload = [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $text,
            'html'    => $html,
        ];

        return $this->postForm($serverAUrl, $payload, ['X-API-Key: ' . $apiKey]);
    }

    private function sendViaTurboSmtp(string $to, string $subject, string $html, string $text): array
    {
        $url            = 'https://api.turbo-smtp.com/api/v2/mail/send';
        $consumerKey    = (string) env('turboSmtp.consumerKey');
        $consumerSecret = (string) env('turboSmtp.consumerSecret');

        if ($consumerKey === '' || $consumerSecret === '') {
            return ['ok' => false, 'message' => 'Konfigurasi email (turboSmtp) belum diatur.'];
        }

        $data = [
            'from'         => 'noreply@kursuscerdas.com',
            'to'           => $to,
            'subject'      => $subject,
            'content'      => $text,
            'html_content' => $html,
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

    private function postForm(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/x-www-form-urlencoded',
        ], $headers));
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
}
