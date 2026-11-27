<?php

namespace App\Libraries;

use App\Models\ContactMessageModel;
use Config\Contact as ContactConfig;

/**
 * Layanan formulir kontak Landing Page ("Hubungi Tim KelasMerdeka").
 *
 * Tanggung jawab:
 *  - memvalidasi input pengunjung (nama, email, WhatsApp, pesan);
 *  - membatasi pengiriman: 1 pesan berhasil / perangkat / 24 jam bergulir
 *    (cookie + session + konfirmasi database) + rate limit IP;
 *  - mencegah double submit (token formulir sekali pakai + lock per IP);
 *  - meneruskan pesan ke email internal melalui MailService yang sudah ada;
 *  - mencatat status pengiriman minimal (tanpa data pribadi).
 *
 * Alamat email penerima hanya dibaca dari konfigurasi/env dan tidak pernah
 * dikembalikan ke view, HTML, JavaScript, maupun response publik.
 */
final class ContactService
{
    public const COOKIE_NAME = 'km_contact_sent';

    private const COOKIE_TTL         = 86400;
    private const S_FORM_TOKEN       = 'contact_form_token';
    private const S_SENT_AT          = 'contact_sent_at';
    private const EMAIL_SUBJECT      = 'Pesan Baru dari Formulir Landing Page KelasMerdeka';
    private const BRAND              = 'KelasMerdeka.com';

    private ContactConfig $config;
    private ContactMessageModel $model;
    private ?MailService $mailer;

    private bool $enabled;
    private string $recipient;
    private int $maxMessageLength;
    private int $captchaExpire;
    private int $dailyLimit;
    private int $ipDailyLimit;
    private int $ipWindowAttempts;
    private int $ipWindowMinutes;

    private ?string $secret = null;

    public function __construct(?ContactConfig $config = null, ?ContactMessageModel $model = null, ?MailService $mailer = null)
    {
        $this->config = $config ?? config('Contact');
        $this->model  = $model ?? new ContactMessageModel();
        $this->mailer = $mailer;

        $this->enabled          = filter_var(env('CONTACT_FORM_ENABLED', $this->config->enabled), FILTER_VALIDATE_BOOL);
        $this->recipient        = trim((string) env('CONTACT_FORM_RECIPIENT', $this->config->recipient));
        $this->maxMessageLength = max(200, min(5000, (int) env('CONTACT_FORM_MAX_MESSAGE_LENGTH', $this->config->maxMessageLength)));
        $this->captchaExpire    = max(60, min(1800, (int) env('CONTACT_FORM_CAPTCHA_EXPIRE', $this->config->captchaExpire)));
        $this->dailyLimit       = max(1, min(10, (int) env('CONTACT_FORM_DAILY_LIMIT', $this->config->dailyLimit)));
        $this->ipDailyLimit     = max(1, min(50, (int) env('CONTACT_FORM_IP_DAILY_LIMIT', $this->config->ipDailyLimit)));
        $this->ipWindowAttempts = max(3, min(100, (int) env('CONTACT_FORM_IP_WINDOW_ATTEMPTS', $this->config->ipWindowAttempts)));
        $this->ipWindowMinutes  = max(1, min(1440, (int) env('CONTACT_FORM_IP_WINDOW_MINUTES', $this->config->ipWindowMinutes)));
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->recipient !== '' && filter_var($this->recipient, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function maxMessageLength(): int
    {
        return $this->maxMessageLength;
    }

    public function captchaTtl(): int
    {
        return $this->captchaExpire;
    }

    /**
     * Status formulir untuk ditampilkan pada Landing Page.
     *
     * @return array{enabled:bool, success:bool, blocked:bool, retry_after:int,
     *               retry_text:string, errors:list<string>, values:array<string,string>,
     *               form_token:string, max_length:int}
     */
    public function state(): array
    {
        $session = session();

        $state = [
            'enabled'     => $this->isEnabled(),
            'success'     => (bool) $session->getFlashdata('contact_success'),
            'blocked'     => false,
            'retry_after' => 0,
            'retry_text'  => '',
            'errors'      => [],
            'values'      => [],
            'form_token'  => $this->formToken(),
            'max_length'  => $this->maxMessageLength,
        ];

        $errors = $session->getFlashdata('contact_errors');
        if (is_array($errors)) {
            $state['errors'] = array_values(array_filter(array_map('strval', $errors)));
        }

        $values = $session->getFlashdata('contact_old');
        if (is_array($values)) {
            $state['values'] = $values;
        }

        // Pesan kegagalan CSRF dari CodeIgniter (flash `error`).
        $flashError = $session->getFlashdata('error');
        if (is_string($flashError) && $flashError !== '') {
            $state['errors'][] = 'Sesi formulir tidak valid. Silakan muat ulang halaman lalu coba lagi.';
        }

        if ($state['enabled'] && ! $state['success']) {
            $block = $this->deviceBlock();
            if ($block['blocked']) {
                $state['blocked']     = true;
                $state['retry_after'] = $block['retry_after'];
                $state['retry_text']  = $this->retryText($block['retry_after']);
            }
        }

        return $state;
    }

    /**
     * Proses pengiriman pesan.
     *
     * @return array{ok:bool, errors:list<string>, message:string, blocked?:bool, retry_after?:int}
     */
    public function send(array $input, string $ip): array
    {
        if (! $this->isEnabled()) {
            return $this->fail(['Formulir kontak sedang tidak tersedia. Silakan coba lagi nanti.']);
        }

        $ipHash = $this->hashValue($ip);

        if (! $this->consumeFormToken((string) ($input['contact_form_token'] ?? ''))) {
            return $this->fail(['Formulir sudah diproses. Silakan muat ulang halaman dan coba lagi.']);
        }

        // Honeypot: field tersembunyi yang hanya diisi bot.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            $this->logAttempt('spam', $ipHash);

            return $this->fail(['Pesan tidak dapat diproses. Silakan muat ulang halaman lalu coba lagi.']);
        }

        $block = $this->deviceBlock();
        if ($block['blocked']) {
            return [
                'ok'          => false,
                'errors'      => [],
                'message'     => 'Anda sudah mengirimkan pesan. ' . $this->retryText($block['retry_after']),
                'blocked'     => true,
                'retry_after' => $block['retry_after'],
            ];
        }

        if ($this->ipDailyExceeded($ipHash) || $this->ipWindowExceeded($ipHash)) {
            return $this->fail(['Terlalu banyak percobaan pengiriman. Silakan coba lagi nanti.']);
        }

        $captcha = new ContactCaptcha($this->captchaExpire);
        $result  = $captcha->verify($input['contact_captcha'] ?? null);
        if ($result !== 'ok') {
            $this->logAttempt('captcha_failed', $ipHash);

            return $this->fail([$this->captchaMessage($result)]);
        }

        [$data, $errors] = $this->validate($input);
        if ($errors !== []) {
            $this->logAttempt('invalid', $ipHash);

            return $this->fail($errors);
        }

        // Serialisasi per IP: mencegah kiriman ganda pada request bersamaan.
        $lock = $this->acquireLock($ipHash);
        if ($lock === null) {
            return $this->fail(['Pesan Anda sedang diproses. Silakan tunggu sebentar.']);
        }

        try {
            $block = $this->deviceBlock();
            if ($block['blocked']) {
                return [
                    'ok'          => false,
                    'errors'      => [],
                    'message'     => 'Anda sudah mengirimkan pesan. ' . $this->retryText($block['retry_after']),
                    'blocked'     => true,
                    'retry_after' => $block['retry_after'],
                ];
            }

            if ($this->ipDailyExceeded($ipHash)) {
                return $this->fail(['Terlalu banyak percobaan pengiriman. Silakan coba lagi nanti.']);
            }

            [$html, $text] = $this->buildEmail($data);

            // Reply-To hanya didukung provider yang mendukungnya (Resend).
            $res = ($this->mailer ?? new MailService())->send(
                $this->recipient,
                self::EMAIL_SUBJECT,
                $html,
                $text,
                $data['email']
            );

            if (! ($res['ok'] ?? false)) {
                $this->logAttempt(
                    'failed',
                    $ipHash,
                    null,
                    isset($res['provider']) ? (string) $res['provider'] : null,
                    isset($res['message']) ? (string) $res['message'] : null
                );

                return $this->fail(['Pesan belum berhasil dikirim. Silakan coba lagi beberapa saat lagi.']);
            }

            $cookie = $this->markDeviceSent($ipHash, isset($res['provider']) ? (string) $res['provider'] : null);

            return ['ok' => true, 'errors' => [], 'message' => 'Pesan berhasil dikirim.', 'cookie' => $cookie];
        } finally {
            $this->releaseLock($lock);
        }
    }

    // ==================== Validasi ====================

    /**
     * @return array{0: array{name:string,email:string,whatsapp:string,message:string}, 1: list<string>}
     */
    private function validate(array $input): array
    {
        $errors = [];

        $name = $this->cleanLine((string) ($input['contact_name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Nama Lengkap wajib diisi.';
        } elseif (mb_strlen($name) < 3) {
            $errors[] = 'Nama Lengkap minimal 3 karakter.';
        } elseif (mb_strlen($name) > 80) {
            $errors[] = 'Nama Lengkap maksimal 80 karakter.';
        }

        $email = trim((string) ($input['contact_email'] ?? ''));
        if ($email === '') {
            $errors[] = 'Alamat Email wajib diisi.';
        } elseif (preg_match('/[\r\n]/', $email) === 1 || mb_strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Format Alamat Email tidak valid.';
        }

        $rawWa   = trim((string) ($input['contact_whatsapp'] ?? ''));
        $whatsapp = $this->normalizeWhatsapp($rawWa);
        if ($rawWa === '') {
            $errors[] = 'Nomor WhatsApp wajib diisi.';
        } elseif ($whatsapp === null) {
            $errors[] = 'Format Nomor WhatsApp tidak valid. Contoh: 081234567890 atau +6281234567890.';
        }

        $message = (string) ($input['contact_message'] ?? '');
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';
        $message = trim($message);

        if ($message === '') {
            $errors[] = 'Pesan Anda wajib diisi.';
        } elseif (mb_strlen($message) < 5) {
            $errors[] = 'Pesan Anda minimal 5 karakter.';
        } elseif (mb_strlen($message) > $this->maxMessageLength) {
            $errors[] = 'Pesan Anda maksimal ' . $this->maxMessageLength . ' karakter.';
        }

        return [
            [
                'name'     => $name,
                'email'    => $email,
                'whatsapp' => $whatsapp ?? '',
                'message'  => $message,
            ],
            $errors,
        ];
    }

    private function cleanLine(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function normalizeWhatsapp(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('/^\+?[0-9\s\-.()]+$/', $raw) !== 1) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';

        if (preg_match('/^62[0-9]{8,13}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^0[0-9]{8,12}$/', $digits) === 1) {
            return '62' . substr($digits, 1);
        }

        return null;
    }

    // ==================== Email ====================

    /**
     * @param array{name:string,email:string,whatsapp:string,message:string} $data
     * @return array{0: string, 1: string} [html, text]
     */
    private function buildEmail(array $data): array
    {
        $name     = esc($data['name']);
        $email    = esc($data['email']);
        $whatsapp = esc($data['whatsapp']);
        $message  = esc($data['message']);
        $time     = esc($this->sendTimeText());
        $brand    = esc(self::BRAND);

        $safeMessageHtml = nl2br($message, false);

        $text = "PESAN BARU - KELASMERDEKA.COM\n\n"
              . "Nama:\n{$data['name']}\n\n"
              . "Email:\n{$data['email']}\n\n"
              . "Nomor WhatsApp:\n{$data['whatsapp']}\n\n"
              . "Pesan:\n{$data['message']}\n\n"
              . "Waktu Pengiriman:\n{$this->sendTimeText()}\n\n"
              . "Sumber:\nFormulir Landing Page KelasMerdeka\n";

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0F172A">'
              . '<h2 style="color:#1D4ED8;margin:0 0 4px">' . $brand . '</h2>'
              . '<p style="margin:0 0 20px;color:#475569;font-size:14px">PESAN BARU - KELASMERDEKA.COM</p>'
              . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #E2E8F0;border-radius:10px;overflow:hidden">'
              . '<tr><td style="padding:10px 14px;background:#F8FAFC;border-bottom:1px solid #E2E8F0;width:170px;font-size:13px;color:#475569">Nama</td><td style="padding:10px 14px;border-bottom:1px solid #E2E8F0;font-size:14px"><strong>' . $name . '</strong></td></tr>'
              . '<tr><td style="padding:10px 14px;background:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;color:#475569">Email</td><td style="padding:10px 14px;border-bottom:1px solid #E2E8F0;font-size:14px">' . $email . '</td></tr>'
              . '<tr><td style="padding:10px 14px;background:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;color:#475569">Nomor WhatsApp</td><td style="padding:10px 14px;border-bottom:1px solid #E2E8F0;font-size:14px">' . $whatsapp . '</td></tr>'
              . '<tr><td style="padding:10px 14px;background:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;color:#475569">Waktu Pengiriman</td><td style="padding:10px 14px;border-bottom:1px solid #E2E8F0;font-size:14px">' . $time . '</td></tr>'
              . '<tr><td style="padding:10px 14px;background:#F8FAFC;font-size:13px;color:#475569">Sumber</td><td style="padding:10px 14px;font-size:14px">Formulir Landing Page KelasMerdeka</td></tr>'
              . '</table>'
              . '<h3 style="margin:24px 0 8px;font-size:15px;color:#0F172A">Pesan:</h3>'
              . '<div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px;font-size:14px;line-height:1.6">' . $safeMessageHtml . '</div>'
              . '<p style="margin:20px 0 0;font-size:12px;color:#94A3B8">Email ini dikirim otomatis oleh formulir kontak landing page. Balas email ini untuk menghubungi pengirim.</p>'
              . '</div>';

        return [$html, $text];
    }

    private function sendTimeText(): string
    {
        try {
            $formatter = new \IntlDateFormatter(
                'id_ID',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::SHORT,
                'Asia/Jakarta',
                \IntlDateFormatter::GREGORIAN,
                "EEEE, d MMMM yyyy 'pukul' HH:mm 'WIB'"
            );

            return (string) $formatter->format(new \DateTime('now', new \DateTimeZone('Asia/Jakarta')));
        } catch (\Throwable $e) {
            return date('d-m-Y H:i') . ' WIB';
        }
    }

    // ==================== Pembatasan pengiriman ====================

    /**
     * @return array{blocked:bool, retry_after:int}
     */
    private function deviceBlock(): array
    {
        $session = session();
        $sentAt  = (int) $session->get(self::S_SENT_AT);

        if ($sentAt > 0 && (time() - $sentAt) < self::COOKIE_TTL) {
            return ['blocked' => true, 'retry_after' => self::COOKIE_TTL - (time() - $sentAt)];
        }

        $cookie = $this->cookieValue();
        if ($cookie === '') {
            return ['blocked' => false, 'retry_after' => 0];
        }

        $expires = $this->cookieExpiry($cookie);
        if ($expires > 0 && $expires <= time()) {
            $this->clearCookie();

            return ['blocked' => false, 'retry_after' => 0];
        }

        $row = $this->model
            ->where('device_hash', $this->hashValue($cookie))
            ->where('status', 'sent')
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($row !== null) {
            $until = strtotime((string) $row['created_at']) + self::COOKIE_TTL;
            if ($until > time()) {
                return ['blocked' => true, 'retry_after' => $until - time()];
            }

            $this->clearCookie();

            return ['blocked' => false, 'retry_after' => 0];
        }

        // Cookie ada tetapi tidak ada catatan server: tetap blokir sampai
        // masa berlaku cookie habis (cookie tidak dipercaya sendirian).
        if ($expires > 0) {
            return ['blocked' => true, 'retry_after' => $expires - time()];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    /**
     * @return array{name:string, value:string, expire:int, path:string, secure:bool, httponly:bool, samesite:string}
     */
    private function markDeviceSent(string $ipHash, ?string $provider): array
    {
        $expires = time() + self::COOKIE_TTL;
        $value   = $expires . '.' . bin2hex(random_bytes(16));

        $this->logAttempt('sent', $ipHash, $this->hashValue($value), $provider);

        session()->set(self::S_SENT_AT, time());

        $secure = function_exists('is_https')
            ? is_https()
            : (service('request')->getUri()->getScheme() === 'https');

        return [
            'name'     => self::COOKIE_NAME,
            'value'    => $value,
            'expire'   => self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function cookieValue(): string
    {
        $cookie = service('request')->getCookie(self::COOKIE_NAME);

        if (is_string($cookie)) {
            return trim($cookie);
        }

        if (is_object($cookie) && method_exists($cookie, 'getValue')) {
            return trim($cookie->getValue());
        }

        return trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
    }

    private function cookieExpiry(string $cookie): int
    {
        $parts = explode('.', $cookie, 2);
        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || preg_match('/^[a-f0-9]{32}$/', $parts[1]) !== 1) {
            return 0;
        }

        return (int) $parts[0];
    }

    private function clearCookie(): void
    {
        service('response')->deleteCookie(self::COOKIE_NAME);
    }

    private function ipDailyExceeded(string $ipHash): bool
    {
        return $this->model
            ->where('ip_hash', $ipHash)
            ->where('status', 'sent')
            ->where('created_at >=', date('Y-m-d H:i:s', time() - self::COOKIE_TTL))
            ->countAllResults() >= $this->ipDailyLimit;
    }

    private function ipWindowExceeded(string $ipHash): bool
    {
        return $this->model
            ->where('ip_hash', $ipHash)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - ($this->ipWindowMinutes * 60)))
            ->countAllResults() >= $this->ipWindowAttempts;
    }

    private function acquireLock(string $ipHash): ?string
    {
        $name = 'km_contact_' . substr(hash('sha256', $ipHash), 0, 40);

        try {
            $row = db_connect()->query('SELECT GET_LOCK(?, 5) AS l', [$name])->getRowArray();
            if ((int) ($row['l'] ?? 0) !== 1) {
                return null;
            }

            return $name;
        } catch (\Throwable $e) {
            log_message('error', 'Contact: gagal membuat lock pengiriman.');

            return null;
        }
    }

    private function releaseLock(string $name): void
    {
        try {
            db_connect()->query('SELECT RELEASE_LOCK(?)', [$name]);
        } catch (\Throwable $e) {
            // Lock dilepas otomatis saat koneksi berakhir.
        }
    }

    // ==================== Token formulir ====================

    private function formToken(): string
    {
        $token = (string) session()->get(self::S_FORM_TOKEN);
        if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        session()->set(self::S_FORM_TOKEN, $token);

        return $token;
    }

    private function consumeFormToken(string $token): bool
    {
        $current = (string) session()->get(self::S_FORM_TOKEN);
        session()->remove(self::S_FORM_TOKEN);

        return $current !== '' && $token !== '' && hash_equals($current, $token);
    }

    // ==================== Utilitas ====================

    private function hashValue(string $value): string
    {
        return hash_hmac('sha256', $value, $this->pepper());
    }

    /**
     * Pepper untuk hash token perangkat/IP. Memakai encryption key bila ada;
     * jika tidak, file privat writable/contact_secret.key dibuat sekali.
     */
    private function pepper(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }

        $key = (string) (config('Encryption')->key ?? '');
        if ($key !== '') {
            return $this->secret = $key;
        }

        $path = WRITEPATH . 'contact_secret.key';
        if (! is_file($path)) {
            file_put_contents($path, bin2hex(random_bytes(32)));
            @chmod($path, 0600);
        }

        return $this->secret = (string) file_get_contents($path);
    }

    private function logAttempt(
        string $status,
        string $ipHash,
        ?string $deviceHash = null,
        ?string $provider = null,
        ?string $note = null
    ): void {
        $note = $note === null ? null : mb_substr(preg_replace('/\s+/', ' ', $note) ?? '', 0, 200);
        if ($note !== null && $this->recipient !== '') {
            $note = str_replace($this->recipient, '[redacted]', $note);
        }

        try {
            $this->model->insert([
                'status'      => $status,
                'device_hash' => $deviceHash,
                'ip_hash'     => $ipHash,
                'provider'    => $provider === null ? null : mb_substr($provider, 0, 20),
                'error_note'  => $note,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Contact: gagal menulis log pengiriman.');
        }
    }

    private function captchaMessage(string $result): string
    {
        return match ($result) {
            'too_many' => 'Terlalu banyak percobaan CAPTCHA. Silakan coba lagi beberapa menit lagi.',
            'expired', 'missing' => 'CAPTCHA sudah kedaluwarsa. Silakan masukkan karakter pada gambar yang baru.',
            default => 'Karakter CAPTCHA tidak sesuai. Silakan coba lagi dengan gambar yang baru.',
        };
    }

    private function retryText(int $seconds): string
    {
        $seconds      = max(0, $seconds);
        $totalMinutes = (int) ceil($seconds / 60);
        $hours        = intdiv($totalMinutes, 60);
        $minutes      = $totalMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return 'Anda dapat mengirimkan pesan baru setelah ' . $hours . ' jam ' . $minutes . ' menit.';
        }

        if ($hours > 0) {
            return 'Anda dapat mengirimkan pesan baru setelah ' . $hours . ' jam.';
        }

        return 'Anda dapat mengirimkan pesan baru setelah ' . max(1, $minutes) . ' menit.';
    }

    /**
     * @param list<string> $errors
     * @return array{ok:bool, errors:list<string>, message:string}
     */
    private function fail(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors, 'message' => $errors[0] ?? 'Pesan belum berhasil dikirim.'];
    }
}
