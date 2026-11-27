<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi formulir kontak Landing Page ("Hubungi Tim KelasMerdeka").
 *
 * Nilai dapat ditimpa melalui `.env` (lihat ContactService). Alamat email
 * penerima TIDAK pernah dikirim ke browser/HTML/JavaScript - hanya dipakai
 * backend saat mengirim email.
 */
class Contact extends BaseConfig
{
    /** Aktif/nonaktifkan formulir kontak tanpa deploy ulang. */
    public bool $enabled = true;

    /** Alamat email internal penerima pesan (default; dapat ditimpa env). */
    public string $recipient = 'gun@putrariau.com';

    /** Panjang maksimum isi pesan (karakter). */
    public int $maxMessageLength = 2000;

    /** Masa berlaku CAPTCHA (detik). */
    public int $captchaExpire = 300;

    /** Maksimum pesan berhasil per perangkat dalam 24 jam bergulir. */
    public int $dailyLimit = 1;

    /** Maksimum pesan berhasil per alamat IP dalam 24 jam bergulir. */
    public int $ipDailyLimit = 10;

    /** Maksimum percobaan POST per alamat IP dalam jendela waktu di bawah. */
    public int $ipWindowAttempts = 10;

    /** Panjang jendela pembatasan percobaan per IP (menit). */
    public int $ipWindowMinutes = 10;
}
