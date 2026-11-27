<?php

namespace App\Models;

use CodeIgniter\Model;

class LimitationAuditLogModel extends Model
{
    protected $table         = 'limitation_audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'admin_id',
        'admin_email',
        'action',
        'lecturer_id',
        'old_value',
        'new_value',
        'created_at',
    ];

    protected $useTimestamps = false;

    public function recent(int $limit = 25): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }
}
