<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpCodeModel extends Model
{
    protected $table         = 'otp_codes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'email',
        'otp_code',
        'expired_at',
        'is_used',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
}
