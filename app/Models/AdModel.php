<?php

namespace App\Models;

use CodeIgniter\Model;

class AdModel extends Model
{
    protected $table         = 'ads';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'title',
        'target_url',
        'file_name',
        'original_name',
        'file_size',
        'is_active',
        'target_gender',
        'priority_score',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    public const GENDER_BOTH      = 'Both';
    public const GENDER_LAKI      = 'Laki-Laki';
    public const GENDER_PEREMPUAN = 'Perempuan';

    public function getActive(string $placement = 'all')
    {
        return $this->where('is_active', 1)
                    ->orderBy('id', 'DESC')
                    ->findAll();
    }

    public function pickRandom(string $placement = 'all'): ?array
    {
        $list = $this->getActive($placement);
        if (empty($list)) {
            return null;
        }
        return $list[array_rand($list)];
    }

    /**
     * Kembalikan daftar target_gender yang diizinkan untuk user dengan
     * gender tertentu. User null/unknown hanya boleh melihat iklan 'Both'.
     *
     * @return string[]
     */
    public static function allowedGendersForUser(?string $userGender): array
    {
        if ($userGender === self::GENDER_LAKI) {
            return [self::GENDER_LAKI, self::GENDER_BOTH];
        }
        if ($userGender === self::GENDER_PEREMPUAN) {
            return [self::GENDER_PEREMPUAN, self::GENDER_BOTH];
        }
        return [self::GENDER_BOTH];
    }

    /**
     * Pilih satu kandidat secara weighted-random berdasarkan priority_score.
     * Jika semua score = 0, fallback ke random biasa.
     *
     * @param array $candidates list of ad rows (harus punya 'id' dan 'priority_score')
     * @return array|null ad row yang terpilih, atau null jika $candidates kosong
     */
    public function pickWeighted(array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $totalWeight = 0;
        foreach ($candidates as $ad) {
            $totalWeight += max(0, (int) ($ad['priority_score'] ?? 0));
        }

        // Jika total bobot = 0 → random biasa dari seluruh kandidat
        if ($totalWeight === 0) {
            return $candidates[array_rand($candidates)];
        }

        $pick = random_int(1, $totalWeight);
        $cumulative = 0;
        foreach ($candidates as $ad) {
            $weight = max(0, (int) ($ad['priority_score'] ?? 0));
            $cumulative += $weight;
            if ($pick <= $cumulative) {
                return $ad;
            }
        }

        // Fallback safety (seharusnya tidak tercapai)
        return $candidates[array_rand($candidates)];
    }

    /**
     * Kurangi priority_score sebuah iklan sebesar 1 secara atomic.
     * Floor di 0 (tidak boleh negatif). Mengembalikan baris ter-update.
     *
     * Catatan: kolom `priority_score` adalah INT UNSIGNED, sehingga ekspresi
     * `priority_score - 1` di MySQL akan overflow ke BIGINT UNSIGNED max saat
     * nilainya 0. Untuk menghindari error "BIGINT UNSIGNED value is out of
     * range", kita filter hanya baris dengan `priority_score > 0` di WHERE.
     */
    public function decrementPriority(int $adId): array
    {
        $db = db_connect();
        $db->query(
            'UPDATE ads SET priority_score = priority_score - 1 WHERE id = ? AND priority_score > 0',
            [$adId]
        );
        $row = $this->find($adId);
        return $row ?: [];
    }
}
