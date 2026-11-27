<?php

namespace App\Libraries;

/**
 * CAPTCHA gambar untuk formulir kontak Landing Page.
 *
 * - Kode 5 karakter (huruf kapital + angka, tanpa karakter ambigu O/0/I/1).
 * - Kode hanya disimpan di session server (tidak pernah dikirim ke HTML/JS/URL).
 * - Berlaku `ttl` detik (default 5 menit), sekali pakai.
 * - Gambar dibuat dinamis oleh server: PNG via GD, atau SVG (bitmap font
 *   internal) sebagai fallback bila ekstensi GD tidak tersedia di hosting.
 * - Percobaan jawaban dibatasi (anti brute force); jawaban salah/kedaluwarsa
 *   selalu menghanguskan kode lama.
 */
final class ContactCaptcha
{
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 5;
    private const MAX_FAILS = 6;
    private const FAIL_WINDOW_SECONDS = 900;
    private const MIN_REISSUE_SECONDS = 1;

    private const S_CODE     = 'contact_captcha_code';
    private const S_EXPIRES  = 'contact_captcha_expires_at';
    private const S_ISSUED   = 'contact_captcha_issued_at';
    private const S_FAILS    = 'contact_captcha_fails';
    private const S_FAIL_AT  = 'contact_captcha_fail_window_at';

    private int $ttl;

    public function __construct(?int $ttl = null)
    {
        $ttl = $ttl ?? (int) env('CONTACT_FORM_CAPTCHA_EXPIRE', config('Contact')->captchaExpire ?? 300);
        $this->ttl = max(60, min(1800, (int) $ttl));
    }

    /** Terbitkan kode baru (dengan throttle agar gambar tidak di-generate terus). */
    public function issue(bool $forceNew = false): void
    {
        $session = session();
        $code    = (string) $session->get(self::S_CODE);
        $issued  = (int) $session->get(self::S_ISSUED);
        $expires = (int) $session->get(self::S_EXPIRES);

        if ($code !== '' && $expires > time() && ! $forceNew) {
            return;
        }

        // Throttle: permintaan gambar baru dalam < 1 detik tetap memakai kode lama.
        if ($code !== '' && $expires > time() && (time() - $issued) < self::MIN_REISSUE_SECONDS) {
            return;
        }

        $session->set(self::S_CODE, $this->randomCode());
        $session->set(self::S_ISSUED, time());
        $session->set(self::S_EXPIRES, time() + $this->ttl);
    }

    /**
     * Render gambar CAPTCHA.
     *
     * @return array{mime: string, body: string}
     */
    public function render(): array
    {
        $code = (string) session()->get(self::S_CODE);
        if ($code === '' || (int) session()->get(self::S_EXPIRES) <= time()) {
            $this->issue();
            $code = (string) session()->get(self::S_CODE);
        }

        return [
            'mime' => function_exists('imagecreatetruecolor') ? 'image/png' : 'image/svg+xml',
            'body' => function_exists('imagecreatetruecolor')
                ? $this->renderPng($code)
                : $this->renderSvg($code),
        ];
    }

    /**
     * Verifikasi jawaban. Kode selalu dihanguskan (sekali pakai), termasuk
     * saat jawaban salah/kedaluwarsa.
     *
     * @return string ok|invalid|expired|missing|too_many
     */
    public function verify(?string $answer): string
    {
        $session = session();

        if ($this->failsExceeded()) {
            return 'too_many';
        }

        $code    = (string) $session->get(self::S_CODE);
        $expires = (int) $session->get(self::S_EXPIRES);

        $session->remove([self::S_CODE, self::S_EXPIRES]);

        if ($code === '') {
            return 'missing';
        }

        if ($expires <= time()) {
            return 'expired';
        }

        $answer = strtoupper(trim((string) $answer));
        if ($answer !== '' && hash_equals($code, $answer)) {
            $session->remove([self::S_FAILS, self::S_FAIL_AT]);

            return 'ok';
        }

        $this->registerFailure();

        return 'invalid';
    }

    public function failsExceeded(): bool
    {
        $session = session();
        $fails   = (int) $session->get(self::S_FAILS);
        $window  = (int) $session->get(self::S_FAIL_AT);

        if ($fails < self::MAX_FAILS) {
            return false;
        }

        if ($window > 0 && (time() - $window) > self::FAIL_WINDOW_SECONDS) {
            $session->remove([self::S_FAILS, self::S_FAIL_AT]);

            return false;
        }

        return true;
    }

    private function registerFailure(): void
    {
        $session = session();
        $fails   = (int) $session->get(self::S_FAILS);
        $window  = (int) $session->get(self::S_FAIL_AT);

        if ($window === 0 || (time() - $window) > self::FAIL_WINDOW_SECONDS) {
            $session->set(self::S_FAILS, 1);
            $session->set(self::S_FAIL_AT, time());

            return;
        }

        $session->set(self::S_FAILS, $fails + 1);
    }

    private function randomCode(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $max     = strlen(self::CHARS) - 1;

        // Pastikan selalu ada minimal satu huruf dan satu angka.
        $chars = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = 2; $i < self::LENGTH; $i++) {
            $chars[] = self::CHARS[random_int(0, $max)];
        }

        shuffle($chars);

        return implode('', $chars);
    }

    // ==================== Render: PNG (GD) ====================

    private function renderPng(string $code): string
    {
        $w = 240;
        $h = 80;

        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);

        $bg = imagecolorallocate($img, 248, 250, 252);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        // Garis & titik gangguan.
        for ($i = 0; $i < 7; $i++) {
            $line = imagecolorallocate($img, random_int(196, 232), random_int(200, 234), random_int(210, 240));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $line);
        }
        for ($i = 0; $i < 220; $i++) {
            $dot = imagecolorallocate($img, random_int(150, 220), random_int(150, 220), random_int(165, 225));
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $dot);
        }

        $fontW = imagefontwidth(5);
        $fontH = imagefontheight(5);
        $scale = 2;
        $len   = max(1, strlen($code));
        $step  = (int) floor(($w - 36) / $len);

        foreach (str_split($code) as $i => $char) {
            $tmp = imagecreatetruecolor($fontW, $fontH);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $fontW, $fontH, $transparent);
            imagealphablending($tmp, true);

            $ink = imagecolorallocate($tmp, random_int(15, 45), random_int(25, 65), random_int(95, 165));
            imagestring($tmp, 5, 0, 0, $char, $ink);

            $dstX = 18 + ($i * $step) + random_int(-2, 2);
            $dstY = random_int(20, 32);
            imagecopyresampled($img, $tmp, $dstX, $dstY, 0, 0, $fontW * $scale, $fontH * $scale, $fontW, $fontH);
            imagedestroy($tmp);
        }

        // Gelombang akhir melintasi teks.
        for ($i = 0; $i < 3; $i++) {
            $wave = imagecolorallocate($img, random_int(120, 180), random_int(140, 195), random_int(180, 225));
            $prevY = random_int(10, $h - 10);
            for ($x = 0; $x < $w; $x += 4) {
                $y = max(2, min($h - 2, $prevY + random_int(-4, 4)));
                imagesetpixel($img, $x, $y, $wave);
                $prevY = $y;
            }
        }

        ob_start();
        imagepng($img);
        $body = (string) ob_get_clean();
        imagedestroy($img);

        return $body;
    }

    // ==================== Render: SVG fallback (tanpa GD) ====================

    /**
     * Font bitmap 5x7 internal. Setiap baris = 5 bit (1 = piksel aktif).
     * Karakter tidak pernah ditulis sebagai teks di SVG - hanya kotak piksel,
     * sehingga jawaban tidak bisa dibaca dari source HTML.
     */
    private const FONT = [
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'B' => ['11110', '10001', '10001', '11110', '10001', '10001', '11110'],
        'C' => ['01110', '10001', '10000', '10000', '10000', '10001', '01110'],
        'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
        'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
        'F' => ['11111', '10000', '10000', '11110', '10000', '10000', '10000'],
        'G' => ['01110', '10001', '10000', '10111', '10001', '10001', '01111'],
        'H' => ['10001', '10001', '10001', '11111', '10001', '10001', '10001'],
        'J' => ['00111', '00010', '00010', '00010', '00010', '10010', '01100'],
        'K' => ['10001', '10010', '10100', '11000', '10100', '10010', '10001'],
        'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
        'M' => ['10001', '11011', '10101', '10101', '10001', '10001', '10001'],
        'N' => ['10001', '11001', '10101', '10011', '10001', '10001', '10001'],
        'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        'Q' => ['01110', '10001', '10001', '10001', '10101', '10010', '01101'],
        'R' => ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
        'S' => ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
        'T' => ['11111', '00100', '00100', '00100', '00100', '00100', '00100'],
        'U' => ['10001', '10001', '10001', '10001', '10001', '10001', '01110'],
        'V' => ['10001', '10001', '10001', '10001', '10001', '01010', '00100'],
        'W' => ['10001', '10001', '10001', '10101', '10101', '11011', '10001'],
        'X' => ['10001', '10001', '01010', '00100', '01010', '10001', '10001'],
        'Y' => ['10001', '10001', '01010', '00100', '00100', '00100', '00100'],
        'Z' => ['11111', '00001', '00010', '00100', '01000', '10000', '11111'],
        '2' => ['01110', '10001', '00001', '00010', '00100', '01000', '11111'],
        '3' => ['11111', '00010', '00100', '00010', '00001', '10001', '01110'],
        '4' => ['00010', '00110', '01010', '10010', '11111', '00010', '00010'],
        '5' => ['11111', '10000', '11110', '00001', '00001', '10001', '01110'],
        '6' => ['00110', '01000', '10000', '11110', '10001', '10001', '01110'],
        '7' => ['11111', '00001', '00010', '00100', '01000', '01000', '01000'],
        '8' => ['01110', '10001', '10001', '01110', '10001', '10001', '01110'],
        '9' => ['01110', '10001', '10001', '01111', '00001', '00010', '01100'],
    ];

    private function renderSvg(string $code): string
    {
        $w     = 240;
        $h     = 80;
        $scale = 8;
        $step  = 42;
        $parts = [];

        $parts[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="CAPTCHA">';
        $parts[] = '<rect width="' . $w . '" height="' . $h . '" fill="#F8FAFC"/>';

        // Gangguan: garis dan titik.
        for ($i = 0; $i < 7; $i++) {
            $parts[] = '<line x1="' . random_int(0, $w) . '" y1="' . random_int(0, $h) . '" x2="' . random_int(0, $w) . '" y2="' . random_int(0, $h) . '" stroke="#C7D2FE" stroke-width="1.2" opacity="0.75"/>';
        }
        for ($i = 0; $i < 90; $i++) {
            $parts[] = '<circle cx="' . random_int(1, $w - 1) . '" cy="' . random_int(1, $h - 1) . '" r="1" fill="#CBD5E1" opacity="0.6"/>';
        }

        $startX = 18;
        foreach (str_split($code) as $i => $char) {
            $glyph = self::FONT[$char] ?? null;
            if ($glyph === null) {
                continue;
            }

            $color  = sprintf('#%02X%02X%02X', random_int(20, 50), random_int(35, 80), random_int(100, 170));
            $rotate = random_int(-7, 7);
            $yShift = random_int(-4, 5);
            $x      = $startX + ($i * $step);
            $y      = 10 + $yShift;

            $parts[] = '<g transform="translate(' . $x . ' ' . $y . ') rotate(' . $rotate . ' 17 28)" fill="' . $color . '">';
            foreach ($glyph as $row => $bits) {
                for ($col = 0; $col < 5; $col++) {
                    if ($bits[$col] === '1') {
                        $parts[] = '<rect x="' . ($col * $scale) . '" y="' . ($row * $scale) . '" width="' . ($scale + 0.6) . '" height="' . ($scale + 0.6) . '" rx="1.2"/>';
                    }
                }
            }
            $parts[] = '</g>';
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }
}
