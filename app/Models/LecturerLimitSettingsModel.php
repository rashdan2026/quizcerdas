<?php

namespace App\Models;

use CodeIgniter\Model;

class LecturerLimitSettingsModel extends Model
{
    protected $table         = 'lecturer_limit_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'max_subjects',
        'max_meetings',
        'max_ai_questions',
        'max_quizzes',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    /** Baris tunggal pengaturan default (dibuat oleh migration). */
    public function singleton(): ?array
    {
        return $this->orderBy('id', 'ASC')->first();
    }
}
