<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Penghitung resend email per alamat (maks 4 resend/hari).
 * Satu counter bersama untuk semua jenis email (OTP/verifikasi) pada
 * alamat yang sama.
 */
class EmailResendCounterModel extends Model
{
    protected $table         = 'email_resend_counters';
    protected $primaryKey    = 'email';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'email',
        'counter_date',
        'resend_count',
        'last_sent_at',
        'next_allowed_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    /**
     * Ambil baris counter dengan row lock (harus dipanggil di dalam transaksi).
     */
    public function forUpdate(string $email): ?array
    {
        $db = db_connect();
        $row = $db->query('SELECT * FROM email_resend_counters WHERE email = ? FOR UPDATE', [$email])->getRowArray();

        return $row;
    }
}
