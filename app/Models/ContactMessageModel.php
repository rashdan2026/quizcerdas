<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Log pengiriman formulir kontak Landing Page.
 *
 * Menyimpan data minimal (TANPA nama/email/WhatsApp/isi pesan):
 * status pengiriman, hash token perangkat, hash IP, provider, dan catatan
 * error yang sudah disanitasi. Data pribadi pengirim hanya diteruskan
 * melalui email ke tim internal.
 */
class ContactMessageModel extends Model
{
    protected $table         = 'contact_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'status',
        'device_hash',
        'ip_hash',
        'provider',
        'error_note',
        'created_at',
    ];

    protected $useTimestamps = false;
}
