<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catatan setiap permintaan resend email.
 * TIDAK menyimpan isi OTP/kode/password — hanya status pengiriman.
 */
class EmailResendLogModel extends Model
{
    protected $table         = 'email_resend_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'email',
        'kind',
        'resend_seq',
        'status',
        'error_note',
        'created_at',
    ];

    protected $useTimestamps = false;
}
