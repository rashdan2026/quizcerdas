<?php

namespace App\Models;

use CodeIgniter\Model;

class PdfFileModel extends Model
{
    protected $table            = 'pdf_files';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'dosen_id',
        'subject_id',
        'judul',
        'deskripsi',
        'file_name',
        'file_size',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
