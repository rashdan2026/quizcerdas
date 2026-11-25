<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAIProviderModel extends Model
{
    protected $table         = 'quiz_ai_providers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'provider_code', 'display_name', 'base_url', 'api_key_encrypted', 'model_name',
        'is_enabled', 'priority_order', 'last_test_status', 'last_test_at',
        'created_at', 'updated_at',
    ];

    public function enabledByPriority(): array
    {
        return $this->where('is_enabled', 1)
            ->where('api_key_encrypted !=', null)
            ->orderBy('priority_order', 'ASC')
            ->findAll();
    }

    public function maskKey(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $len = strlen($plain);

        return substr($plain, 0, min(4, $len)) . str_repeat('*', max(4, $len - 8)) . substr($plain, -4);
    }
}
