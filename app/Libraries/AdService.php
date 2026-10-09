<?php

namespace App\Libraries;

use App\Models\AdImpressionModel;
use App\Models\AdModel;
use App\Models\AppSettingModel;

class AdService
{
    /**
     * Buffer tambahan (detik) untuk window "current view" — saat mahasiswa
     * refresh dalam window ini, mereka melihat iklan yang SAMA tanpa
     * impression baru. Window = lock duration + buffer (cukup untuk
     * refresh saat countdown).
     */
    private const CURRENT_VIEW_BUFFER = 10;

    /** Session key untuk tracking iklan terakhir yang ditampilkan */
    private const SESSION_LAST_SHOWN = 'ad_last_shown_id';

    protected AdModel $adModel;
    protected AdImpressionModel $impressionModel;
    protected AppSettingModel $settingModel;

    public function __construct()
    {
        $this->adModel        = new AdModel();
        $this->impressionModel = new AdImpressionModel();
        $this->settingModel    = new AppSettingModel();
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settingModel->getValue($key, $default);
    }

    public function getLockSeconds(): int
    {
        return (int) $this->getSetting('ad_lock_duration_seconds', 10);
    }

    public function getDailyMax(): int
    {
        return (int) $this->getSetting('daily_ad_max_display', 2);
    }

    public function getDefaultSettingNumber(): int
    {
        $val = (int) $this->getSetting('default_ad_setting_number', 5);
        return $val >= 1 && $val <= 9 ? $val : 5;
    }

    public function getMaxFileSizeMb(): int
    {
        return (int) $this->getSetting('ad_max_file_size_mb', 1);
    }

    public function shouldShowNow(?int $settingNumber = null, ?string $nowSecond = null): bool
    {
        $sn = $settingNumber ?? $this->getDefaultSettingNumber();
        if ($sn < 1) {
            $sn = 1;
        }
        $second = $nowSecond !== null ? (int) $nowSecond : (int) date('s');
        return ($second % $sn) === 0;
    }

    public function getUserIdentifier(): string
    {
        $session = session();
        if ($session->get('logged_in')) {
            $role = $session->get('role');
            $uid  = $session->get('user_id') ?? $session->get('user_email');
            if ($uid) {
                return $role . ':' . $uid;
            }
        }
        if (! $session->has('ad_anon_id')) {
            $session->set('ad_anon_id', bin2hex(random_bytes(8)));
        }
        return 'anon:' . $session->get('ad_anon_id');
    }

    public function pickAdForDisplay(string $placement): ?array
    {
        $session = session();

        // 1) Anti-refresh-during-countdown: jika user masih dalam window
        //    "active view" (lock duration + buffer), kembalikan iklan yang
        //    sama TANPA menambah impression baru. Ini mencegah abuse refresh
        //    untuk skip iklan atau meng-inflate hitungan harian.
        $currentView = $session->get('current_ad_view');
        $window      = $this->getLockSeconds() + self::CURRENT_VIEW_BUFFER;
        if (is_array($currentView)
            && ! empty($currentView['ad_id'])
            && isset($currentView['viewed_at'])
            && (time() - (int) $currentView['viewed_at']) < $window
        ) {
            $cachedAd = $this->adModel->find((int) $currentView['ad_id']);
            if ($cachedAd && (int) $cachedAd['is_active'] === 1) {
                return $cachedAd; // SAME ad, no new impression
            }
        }

        // 2) Normal flow: cek kandidat iklan + batas harian
        $ads = $this->adModel->getActive($placement);
        if (empty($ads)) {
            return null;
        }

        // Exclude iklan yang baru saja ditampilkan supaya tidak berturut-turut
        // muncul iklan yang sama (rotasi lebih natural saat navigasi halaman).
        $lastShown = (int) $session->get(self::SESSION_LAST_SHOWN);
        if ($lastShown > 0 && count($ads) > 1) {
            $ads = array_values(array_filter(
                $ads,
                fn($a) => (int) $a['id'] !== $lastShown
            ));
        }

        $identifier = $this->getUserIdentifier();
        $todayCount = $this->impressionModel->countToday($identifier, $placement);
        if ($todayCount >= $this->getDailyMax()) {
            return null;
        }

        // Frekuensi tampil dikontrol global oleh default_ad_setting_number.
        // Per-ad setting_number TIDAK memblokir (hanya disimpan sebagai metadata)
        // supaya semua iklan aktif punya kesempatan tampil yang merata.
        $defaultSn = $this->getDefaultSettingNumber();
        if (! $this->shouldShowNow($defaultSn)) {
            return null;
        }

        // Acak urutan iklan lalu pilih satu — menjamin variasi.
        shuffle($ads);
        $ad = $ads[0];

        $this->impressionModel->recordView((int) $ad['id'], $identifier, $placement);

        // 3) Simpan ke session untuk anti-refresh & rotasi
        $session->set('current_ad_view', [
            'ad_id'    => (int) $ad['id'],
            'viewed_at' => time(),
        ]);
        $session->set(self::SESSION_LAST_SHOWN, (int) $ad['id']);

        return $ad;
    }

    /**
     * Hapus "current view" dari session (tapi pertahankan "last shown"
     * agar halaman berikutnya bisa exclude iklan yang sama).
     * Panggil setelah login berhasil agar navigasi berikutnya
     * melihat iklan berbeda (tidak carry over dari halaman login).
     */
    public function clearCurrentView(): void
    {
        session()->remove('current_ad_view');
    }
}
