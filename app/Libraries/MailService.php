<?php

namespace App\Libraries;

use GuzzleHttp\Client as GuzzleClient;
use Resend\Client as ResendClient;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

/**
 * Gateway pengiriman email aplikasi.
 *
 * Provider yang didukung:
 *  - resend   : Resend API (SDK resmi resend/resend-php)
 *  - gmail    : Gmail API yang sudah terdaftar (layanan gmail.webriau.com)
 *  - webriau  : Webriau.com API (endpoint yang sama, dipertahankan apa adanya)
 *
 * Mode:
 *  - MAIL_GATEWAY_ENABLED=true  → provider dipilih MAIL_PROVIDER. Khusus
 *    `resend`, jika Resend mengembalikan error (termasuk 429/kuota), email
 *    otomatis dialihkan ke Gmail/Webriau API.
 *  - MAIL_GATEWAY_ENABLED=false → perilaku lama dipertahankan: Webriau/Gmail API
 *    dulu, lalu fallback Resend.
 *
 * Tidak pernah mencatat API key, isi email (OTP/password), atau detail internal
 * ke log/pesan pengguna.
 */
class MailService
{
    public const PROVIDER_RESEND  = 'resend';
    public const PROVIDER_GMAIL   = 'gmail';
    public const PROVIDER_WEBRIAU = 'webriau';

    /** Nama penyedia "Gmail API" yang dipakai aplikasi (host gmail.webriau.com). */
    private const LEGACY_GMAIL_PROVIDER = self::PROVIDER_WEBRIAU;

    private ?string $providerOverride;
    private ?bool $enabledOverride;

    /**
     * @param string|null $provider Paksa provider tertentu (untuk pengujian/CLI).
     * @param bool|null   $enabled  Paksa status gateway (untuk pengujian/CLI).
     */
    public function __construct(?string $provider = null, ?bool $enabled = null)
    {
        $this->providerOverride = $provider;
        $this->enabledOverride  = $enabled;
    }

    /**
     * Kirim email. Hasil selalu berbentuk:
     * ['ok' => bool, 'message' => string, 'provider' => string|null, 'id' => string|null]
     */
    public function send(string $to, string $subject, string $html, ?string $text = null): array
    {
        $to = trim($to);
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->result(false, 'Alamat email penerima tidak valid.', null);
        }

        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        if ($subject === '') {
            return $this->result(false, 'Subjek email tidak boleh kosong.', null);
        }

        $html = $html !== '' ? $html : $this->toHtml($text ?? '');
        $text = $text ?? trim(strip_tags($html));

        if ($this->isEnabled()) {
            $provider = $this->resolveProvider();
            if ($provider === null) {
                return $this->result(false, 'Konfigurasi MAIL_PROVIDER tidak dikenal.', null);
            }

            $result = $this->sendVia($provider, $to, $subject, $html, $text);
            if ($result['ok']) {
                return $result;
            }

            // Resend diprioritaskan; jika error (termasuk 429/kuota), alihkan
            // ke Gmail/Webriau API agar email tetap terkirim.
            if ($provider === self::PROVIDER_RESEND) {
                log_message('error', 'MailGateway: Resend gagal -> fallback Gmail/Webriau.');

                $fallback = $this->sendVia(self::PROVIDER_WEBRIAU, $to, $subject, $html, $text);
                if ($fallback['ok']) {
                    $fallback['message'] = 'OK (via Gmail/Webriau — fallback dari Resend)';

                    return $fallback;
                }

                log_message('error', 'MailGateway: Resend dan Gmail/Webriau sama-sama gagal.');
            }

            return $this->result(
                false,
                'Gagal mengirim email melalui ' . $provider . ' maupun Gmail/Webriau.',
                $provider
            );
        }

        // Mode lama (gateway nonaktif): Gmail/Webriau dulu, fallback Resend
        // (menggantikan posisi TurboSMTP pada implementasi sebelumnya).
        $primary = $this->sendVia(self::LEGACY_GMAIL_PROVIDER, $to, $subject, $html, $text);
        if ($primary['ok']) {
            return $primary;
        }

        $fallback = $this->sendVia(self::PROVIDER_RESEND, $to, $subject, $html, $text);
        if ($fallback['ok']) {
            return $fallback;
        }

        return $this->result(
            false,
            'Gagal mengirim email melalui Gmail/Webriau maupun Resend.',
            null
        );
    }

    private function sendVia(string $provider, string $to, string $subject, string $html, string $text): array
    {
        return match ($provider) {
            self::PROVIDER_RESEND  => $this->sendViaResend($to, $subject, $html, $text),
            self::PROVIDER_WEBRIAU => $this->sendViaWebriau($to, $subject, $html, $text),
            // Aplikasi memakai satu layanan Gmail API (gmail.webriau.com).
            // Nilai "gmail" dipertahankan agar kompatibel dengan konfigurasi lama.
            self::PROVIDER_GMAIL   => $this->sendViaWebriau($to, $subject, $html, $text),
            default                => $this->result(false, 'Provider email tidak dikenal.', $provider),
        };
    }

    private function resolveProvider(): ?string
    {
        $provider = $this->providerOverride;
        if ($provider === null) {
            $provider = (string) env('MAIL_PROVIDER', self::PROVIDER_WEBRIAU);
        }
        $provider = strtolower(trim($provider));

        return in_array($provider, [self::PROVIDER_RESEND, self::PROVIDER_GMAIL, self::PROVIDER_WEBRIAU], true)
            ? $provider
            : null;
    }

    private function isEnabled(): bool
    {
        if ($this->enabledOverride !== null) {
            return $this->enabledOverride;
        }

        return filter_var(env('MAIL_GATEWAY_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }

    // ==================== Resend ====================

    private function sendViaResend(string $to, string $subject, string $html, string $text): array
    {
        $apiKey = trim((string) env('RESEND_API_KEY', ''));
        if ($apiKey === '') {
            return $this->result(false, 'Konfigurasi email Resend belum lengkap (RESEND_API_KEY kosong).', self::PROVIDER_RESEND);
        }

        $apiUrl    = trim((string) env('RESEND_API_URL', 'https://api.resend.com'));
        $fromEmail = trim((string) env('MAIL_FROM_ADDRESS', ''));
        $fromName  = trim((string) env('MAIL_FROM_NAME', ''));

        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->result(false, 'Konfigurasi MAIL_FROM_ADDRESS belum valid.', self::PROVIDER_RESEND);
        }

        if (! class_exists(ResendClient::class)) {
            return $this->result(false, 'SDK Resend belum terpasang di server.', self::PROVIDER_RESEND);
        }

        try {
            $client = new ResendClient(new HttpTransporter(
                new GuzzleClient([
                    'timeout'         => (float) env('MAIL_TIMEOUT_SECONDS', 15),
                    'connect_timeout' => (float) env('MAIL_CONNECT_TIMEOUT_SECONDS', 8),
                ]),
                BaseUri::from($apiUrl),
                Headers::withAuthorization(ApiKey::from($apiKey))
            ));

            $result = $client->emails->send([
                'from'    => $this->formatFrom($fromName, $fromEmail),
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
            ]);

            $id = $result->id ?? null;

            return $this->result(true, 'OK (via Resend)', self::PROVIDER_RESEND, is_string($id) ? $id : null);
        } catch (\Resend\Exceptions\ErrorException $e) {
            // Error dari API Resend (mis. 429 kuota/rate limit, 401/403 auth
            // atau domain belum diverifikasi, 422 parameter). Kode & tipe
            // dicatat untuk diagnosis; isi email tidak pernah dicatat.
            $status = $e->getErrorCode();
            $type   = $e->getErrorType();
            log_message('error', 'MailGateway: Resend API error {status} ({type})', [
                'status' => (string) $status,
                'type'   => $type,
            ]);

            $message = match (true) {
                $status === 429 => 'Kuota/limit pengiriman email Resend tercapai.',
                in_array($status, [401, 403], true) => 'Autentikasi atau domain pengirim Resend ditolak.',
                $status === 422 => 'Parameter email ditolak layanan Resend.',
                default => 'Resend menolak permintaan pengiriman email (' . $this->safeExceptionMessage($e->getMessage()) . ').',
            };

            return $this->result(false, $message, self::PROVIDER_RESEND);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            log_message('error', 'MailGateway: Resend koneksi gagal.');

            return $this->result(false, 'Koneksi ke layanan email Resend timeout/gagal.', self::PROVIDER_RESEND);
        } catch (\Throwable $e) {
            log_message('error', 'MailGateway: Resend exception: {type}', ['type' => get_class($e)]);

            return $this->result(false, 'Terjadi kesalahan pada layanan email Resend.', self::PROVIDER_RESEND);
        }
    }

    // ==================== Gmail / Webriau API ====================
    // Implementasi lama dipertahankan (endpoint, autentikasi, parameter).

    private function sendViaWebriau(string $to, string $subject, string $html, string $text): array
    {
        if (! function_exists('curl_init')) {
            return $this->result(false, 'Ekstensi cURL tidak tersedia di server.', self::PROVIDER_WEBRIAU);
        }

        $url    = (string) env('otp.mailApiUrl', 'https://gmail.webriau.com/servera.php');
        $apiKey = (string) env('otp.mailApiKey', '');
        if ($url === '' || $apiKey === '') {
            return $this->result(false, 'Konfigurasi email Gmail/Webriau belum lengkap.', self::PROVIDER_WEBRIAU);
        }

        $payload = [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $text,
        ];
        if ($html !== '') {
            $payload['html'] = $html;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-Key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) env('MAIL_TIMEOUT_SECONDS', 15));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) env('MAIL_CONNECT_TIMEOUT_SECONDS', 8));

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            log_message('error', 'MailGateway: Webriau/Gmail koneksi gagal.');

            return $this->result(false, 'Koneksi ke layanan email timeout/gagal.', self::PROVIDER_WEBRIAU);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $responseData = json_decode((string) $response, true);
            $errorText = is_array($responseData) && isset($responseData['message'])
                ? $this->safeExceptionMessage((string) $responseData['message'])
                : 'HTTP ' . $httpCode;
            log_message('error', 'MailGateway: Webriau/Gmail HTTP {code}', ['code' => $httpCode]);

            return $this->result(false, 'Server email menolak permintaan (' . $errorText . ').', self::PROVIDER_WEBRIAU);
        }

        return $this->result(true, 'OK', self::PROVIDER_WEBRIAU);
    }

    // ==================== Utilitas ====================

    private function formatFrom(string $name, string $email): string
    {
        $name = trim(str_replace(["\r", "\n", '<', '>', '"'], '', $name));

        return $name === '' ? $email : $name . ' <' . $email . '>';
    }

    private function toHtml(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Ringkas pesan eksepsi agar aman ditampilkan/di-log:
     * tanpa newline, dipotong, dan tanpa potongan API key.
     */
    private function safeExceptionMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', $message) ?? '';
        $message = str_replace((string) env('RESEND_API_KEY', ''), '[redacted]', $message);

        return mb_substr(trim($message), 0, 160);
    }

    private function result(bool $ok, string $message, ?string $provider, ?string $id = null): array
    {
        return [
            'ok'       => $ok,
            'message'  => $message,
            'provider' => $provider,
            'id'       => $id,
        ];
    }
}
