<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table            = 'students';
    protected $primaryKey       = 'email';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'npm',
        'email',
        'nama',
        'jenkel',
        'password',
        'passwd',
        'kelas',
        'no_whatsapp',
        'profile_updated_at',
        'last_login',
        'login_count',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
