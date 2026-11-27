<?php

namespace App\Models;

use CodeIgniter\Model;

class LecturerLimitOverrideModel extends Model
{
    protected $table         = 'lecturer_limit_overrides';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'lecturer_id',
        'max_subjects',
        'max_meetings',
        'max_ai_questions',
        'max_quizzes',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    public function forLecturer(int $lecturerId): ?array
    {
        return $this->where('lecturer_id', $lecturerId)->first();
    }
}
