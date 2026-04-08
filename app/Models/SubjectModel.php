<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectModel extends Model
{
    protected $table         = 'subjects';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'kode_mk',
        'nama_mk',
        'is_active',
        'dosen_id',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
