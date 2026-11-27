<?php

namespace App\Models;

use CodeIgniter\Model;

class LecturerModel extends Model
{
    protected $table         = 'lecturers';
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
        'is_active',
        'email_verified',
        'profile_confirmed',
        'profile_confirmed_at',
        'last_login',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
