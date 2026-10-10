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

    /**
     * Pilih iklan untuk ditampilkan. Mengikuti aturan:
     *  1. Anti-refresh-during-countdown (kembalikan iklan SAMA tanpa impression baru)
     *  2. Hanya iklan aktif
     *  3. Filter target_gender sesuai user (null → hanya 'Both')
     *  4. Exclude iklan yang sudah dilihat user hari ini (anti-repeat)
     *  5. Exclude ad_last_shown (rotasi)
     *  6. Daily quota
     *  7. Global frequency gate (detik % N == 0)
     *  8. Weighted-random berdasar priority_score (fallback random biasa bila semua 0)
     *  9. Record impression + decrementPriority
     */
    public function pickAdForDisplay(string $placement, ?string $userGender = null): ?array
    {
        $session = session();

        // 1) Anti-refresh-during-countdown: iklan SAMA tanpa impression baru
        $currentView = $session->get('current_ad_view');
        $window      = $this->getLockSeconds() + self::CURRENT_VIEW_BUFFER;
        if (is_array($currentView)
            && ! empty($currentView['ad_id'])
            && isset($currentView['viewed_at'])
            && (time() - (int) $currentView['viewed_at']) < $window
        ) {
            $cachedAd = $this->adModel->find((int) $currentView['ad_id']);
            if ($cachedAd && (int) $cachedAd['is_active'] === 1) {
                return $cachedAd;
            }
        }

        // 2) Kandidat aktif
        $ads = $this->adModel->getActive($placement);
        if (empty($ads)) {
            return null;
        }

        // 3) Filter target_gender
        $allowedGenders = AdModel::allowedGendersForUser($userGender);
        $ads = array_values(array_filter(
            $ads,
            fn($a) => in_array((string) ($a['target_gender'] ?? AdModel::GENDER_BOTH), $allowedGenders, true)
        ));
        if (empty($ads)) {
            return null;
        }

        // 4) Anti-repeat harian: exclude iklan yang sudah dilihat user hari ini
        $identifier = $this->getUserIdentifier();
        $seenIds    = $this->impressionModel->seenTodayAdIds($identifier);
        if (! empty($seenIds)) {
            $ads = array_values(array_filter(
                $ads,
                fn($a) => ! in_array((int) $a['id'], $seenIds, true)
            ));
            if (empty($ads)) {
                return null;
            }
        }

        // 5) Exclude ad_last_shown supaya tidak berturut-turut
        $lastShown = (int) $session->get(self::SESSION_LAST_SHOWN);
        if ($lastShown > 0 && count($ads) > 1) {
            $ads = array_values(array_filter(
                $ads,
                fn($a) => (int) $a['id'] !== $lastShown
            ));
        }

        // 6) Daily quota user — GLOBAL per user per hari (semua placement
        //    digabung: dashboard + pdf), sesuai deskripsi setting admin
        //    "Maks tampil: Nx per hari per user". (v5.8.5: sebelumnya
        //    dihitung per-placement sehingga kuota dashboard tidak pernah
        //    terpicu karena bug ENUM placement kosong.)
        $todayCount = $this->impressionModel->countTodayAllPlacements($identifier);
        if ($todayCount >= $this->getDailyMax()) {
            return null;
        }

        // 7) Global frequency gate
        $defaultSn = $this->getDefaultSettingNumber();
        if (! $this->shouldShowNow($defaultSn)) {
            return null;
        }

        // 8) Weighted-random (fallback random biasa bila semua score 0)
        $ad = $this->adModel->pickWeighted($ads);
        if (! $ad) {
            return null;
        }

        // 9) Record impression + decrement priority (atomic per-row)
        $this->impressionModel->recordView((int) $ad['id'], $identifier, $placement);
        $this->adModel->decrementPriority((int) $ad['id']);

        // Simpan ke session untuk anti-refresh & rotasi
        $session->set('current_ad_view', [
            'ad_id'     => (int) $ad['id'],
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
