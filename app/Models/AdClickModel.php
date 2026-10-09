<?php

namespace App\Models;

use CodeIgniter\Model;

class AdClickModel extends Model
{
    protected $table         = 'ad_clicks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'ad_id',
        'clicked_at',
        'view_date',
    ];

    protected $useTimestamps = false;

    public function recordClick(int $adId): void
    {
        $this->insert([
            'ad_id'      => $adId,
            'clicked_at' => date('Y-m-d H:i:s'),
            'view_date'  => date('Y-m-d'),
        ]);
    }

    public function countByAdSince(string $startDate): array
    {
        $rows = $this->db->table('ad_clicks')
            ->select('ad_id, COUNT(*) AS clicks')
            ->where('view_date >=', $startDate)
            ->groupBy('ad_id')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['ad_id']] = (int) $r['clicks'];
        }
        return $out;
    }

    public function totalClicksSince(string $startDate): int
    {
        $row = $this->db->table('ad_clicks')
            ->select('COUNT(*) AS total')
            ->where('view_date >=', $startDate)
            ->get()
            ->getRowArray();

        return (int) ($row['total'] ?? 0);
    }
}
