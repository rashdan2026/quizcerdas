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
        'password',
        'is_active',
        'last_login',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
