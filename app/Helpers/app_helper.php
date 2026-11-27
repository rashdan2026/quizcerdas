<?php

/**
 * Helper nama aplikasi: SATU sumber kebenaran dari database (app_settings.app_name),
 * dapat diubah admin melalui menu Settings tanpa deploy ulang.
 */

if (! function_exists('app_name')) {
    /**
     * Nama aplikasi untuk semua label UI, judul halaman, email, dan footer.
     * Fallback dipakai hanya bila setting belum ada / kosong di database.
     * Cache per-request: dibaca ulang tiap request PHP baru (tanpa risiko data basi).
     */
    function app_name(): string
    {
        if (array_key_exists('__app_name_cache', $GLOBALS)) {
            return $GLOBALS['__app_name_cache'];
        }

        $value = null;
        try {
            $value = (new \App\Models\AppSettingModel())->getValue('app_name');
        } catch (\Throwable $e) {
            // Database belum siap (mis. instalasi baru): pakai default, jangan crash.
        }

        $value = trim((string) $value);

        return $GLOBALS['__app_name_cache'] = ($value !== '' ? $value : 'Sistem Absensi Kampus');
    }
}

if (! function_exists('reset_app_name_cache')) {
    /**
     * Bersihkan cache nama aplikasi — dipanggil setelah admin menyimpan setting
     * agar perubahan nama langsung terlihat di request yang sama.
     */
    function reset_app_name_cache(): void
    {
        unset($GLOBALS['__app_name_cache']);
    }
}
