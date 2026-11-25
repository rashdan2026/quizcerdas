<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAILogModel extends Model
{
    protected $table         = 'quiz_ai_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'batch_id', 'quiz_id', 'lecturer_id', 'provider_id', 'provider_snapshot',
        'model_snapshot', 'attempt_number', 'is_fallback', 'status', 'error_category',
        'http_status', 'latency_ms', 'requested_count', 'received_count',
        'duplicate_count', 'token_prompt', 'token_completion', 'created_at',
    ];

    public function latest(int $limit = 100): array
    {
        return $this->orderBy('id', 'DESC')->limit($limit)->findAll();
    }
}
