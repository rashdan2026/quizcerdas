<?php

namespace App\Models;

use CodeIgniter\Model;

class MeetingModel extends Model
{
    protected $table         = 'meetings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'subject_id',
        'pertemuan_ke',
        'judul',
        'deskripsi',
        'pdf_file_id',
        'token_qr',
        'expired_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    // Soft delete: pertemuan yang dihapus tetap terhitung untuk limit
    // pembuatan per mata kuliah (anti-abuse), tapi tidak tampil di aplikasi.
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
}
