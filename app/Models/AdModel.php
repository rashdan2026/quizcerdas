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
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    public function getActive(string $placement = 'all')
    {
        // Placement sekarang universal — semua iklan aktif tampil di semua halaman (login + PDF).
        // Parameter $placement dipertahankan untuk backward-compat tapi tidak digunakan.
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
}
