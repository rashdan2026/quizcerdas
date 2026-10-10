<?php

namespace App\Models;

use CodeIgniter\Model;

class AdImpressionModel extends Model
{
    protected $table         = 'ad_impressions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'ad_id',
        'user_identifier',
        'placement',
        'view_date',
        'view_count',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    public function countToday(string $userIdentifier, string $placement = 'all'): int
    {
        $row = $this->selectSum('view_count', 'total')
            ->where('user_identifier', $userIdentifier)
            ->where('view_date', date('Y-m-d'))
            ->whereIn('placement', $placement === 'all' ? ['login','pdf','dashboard','all'] : [$placement, 'all'])
            ->first();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Hitung TOTAL impresi user hari ini dari SEMUA placement (login + pdf +
     * dashboard) — dipakai untuk kuota harian global per-user, sesuai
     * deskripsi setting admin "Maks tampil: Nx per hari per user".
     * (v5.8.5: sebelumnya kuota dihitung per-placement sehingga user bisa
     * melihat Nx iklan dashboard + Nx iklan pdf = 2N total per hari.)
     */
    public function countTodayAllPlacements(string $userIdentifier): int
    {
        $row = $this->selectSum('view_count', 'total')
            ->where('user_identifier', $userIdentifier)
            ->where('view_date', date('Y-m-d'))
            ->first();

        return (int) ($row['total'] ?? 0);
    }

    public function recordView(int $adId, string $userIdentifier, string $placement): void
    {
        $today = date('Y-m-d');
        $existing = $this->where('ad_id', $adId)
            ->where('user_identifier', $userIdentifier)
            ->where('view_date', $today)
            ->where('placement', $placement)
            ->first();

        if ($existing) {
            $this->update($existing['id'], [
                'view_count' => $existing['view_count'] + 1,
            ]);
        } else {
            $this->insert([
                'ad_id'           => $adId,
                'user_identifier' => $userIdentifier,
                'placement'       => $placement,
                'view_date'       => $today,
                'view_count'      => 1,
            ]);
        }
    }

    /**
     * Kembalikan daftar ad_id yang SUDAH dilihat user tertentu pada hari ini
     * (semua placement). Dipakai untuk anti-repeat harian.
     *
     * @return int[]
     */
    public function seenTodayAdIds(string $userIdentifier): array
    {
        $rows = $this->db->table('ad_impressions')
            ->select('ad_id')
            ->where('user_identifier', $userIdentifier)
            ->where('view_date', date('Y-m-d'))
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r['ad_id'];
        }
        return $ids;
    }
}
