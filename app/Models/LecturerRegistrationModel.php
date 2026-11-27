<?php

namespace App\Models;

use CodeIgniter\Model;

class LecturerRegistrationModel extends Model
{
    protected $table         = 'lecturer_registrations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'nama',
        'email',
        'jenis_kelamin',
        'tanggal_lahir',
        'nama_kampus',
        'password',
        'verify_code_hash',
        'verify_expires_at',
        'verify_attempts',
        'resend_available_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
