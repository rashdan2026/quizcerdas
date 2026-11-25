<?php

namespace App\Libraries\Quiz;

/**
 * Enkripsi/dekripsi API key menggunakan secret server-side.
 * Secret diambil dari env `quiz.apiKeySecret`, atau file private
 * writable/quiz_secret.key yang dibuat otomatis sekali.
 */
final class QuizCrypto
{
    private static ?string $secret = null;

    private static function secret(): string
    {
        if (self::$secret !== null) {
            return self::$secret;
        }

        $env = env('quiz.apiKeySecret');
        if (is_string($env) && $env !== '') {
            self::$secret = hash('sha256', $env, true);

            return self::$secret;
        }

        $path = WRITEPATH . 'quiz_secret.key';
        if (! is_file($path)) {
            $raw = random_bytes(32);
            file_put_contents($path, bin2hex($raw));
            @chmod($path, 0600);
        }

        self::$secret = hash('sha256', (string) file_get_contents($path), true);

        return self::$secret;
    }

    public static function encrypt(string $plain): string
    {
        $key = self::secret();
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(?string $blob): ?string
    {
        if ($blob === null || $blob === '') {
            return null;
        }

        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $pt  = openssl_decrypt($ct, 'aes-256-gcm', self::secret(), OPENSSL_RAW_DATA, $iv, $tag);

        return $pt === false ? null : $pt;
    }
}
