<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAttemptAnswerModel extends Model
{
    protected $table         = 'quiz_attempt_answers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id', 'question_id', 'selected_option', 'is_correct',
        'points_awarded', 'started_at', 'answered_at', 'response_time_ms', 'created_at',
    ];

    protected function idempotentInsert(array $data): bool
    {
        $builder = $this->db->table('quiz_attempt_answers');
        try {
            return (bool) $builder->insert($data);
        } catch (\Throwable $e) {
            // Unique constraint attempt_id+question_id: jawaban sudah ada, abaikan (idempotent).
            return false;
        }
    }

    public function saveAnswer(array $data): bool
    {
        return $this->idempotentInsert($data);
    }

    public function forAttempt(int $attemptId): array
    {
        return $this->where('attempt_id', $attemptId)->findAll();
    }
}
