<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAttemptQuestionMapModel extends Model
{
    protected $table         = 'quiz_attempt_question_map';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id', 'question_id', 'sort_order', 'points', 'penalty',
        'snapshot', 'option_order', 'duration_seconds', 'question_started_at',
        'question_deadline_at', 'is_expired', 'created_at',
    ];

    public function forAttempt(int $attemptId): array
    {
        return $this->where('attempt_id', $attemptId)->orderBy('sort_order', 'ASC')->findAll();
    }
}
