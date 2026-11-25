<?php

namespace App\Libraries\Quiz;

use App\Models\Quiz\QuizAIProviderModel;
use App\Models\Quiz\QuizAILogModel;

/**
 * Satu service AI pusat: eksekusi request dengan fallback sesuai
 * urutan prioritas provider yang dikonfigurasi admin.
 */
class AIProviderManager
{
    private QuizAILogModel $logModel;

    /** Cache provider per instance: satu proses generate tidak perlu membaca tabel provider berulang. */
    private ?array $providers = null;

    public function __construct(?QuizAILogModel $logModel = null)
    {
        $this->logModel = $logModel ?? new QuizAILogModel();
    }

    public function enabledProviders(): array
    {
        if ($this->providers === null) {
            $this->providers = (new QuizAIProviderModel())->enabledByPriority();
        }

        return $this->providers;
    }

    /**
     * @param array{batch_id:?int,quiz_id:?int,lecturer_id:?int,
     *  requested_count:int,received_count:int,duplicate_count:int} $ctx
     * @param array{max_tokens?:int,timeout?:int,temperature?:float,reasoning?:bool|null} $opts
     */
    public function chatJsonWithFallback(string $systemPrompt, string $userPrompt, array $ctx, int $attemptNumber = 1, array $opts = []): array
    {
        $providers = $this->enabledProviders();
        $last      = null;

        foreach ($providers as $i => $row) {
            $provider = $this->makeProvider($row);
            if ($provider === null) {
                continue;
            }

            $res = $provider->chatJson($systemPrompt, $userPrompt, (int) ($opts['timeout'] ?? 120), $opts);

            $this->logModel->insert([
                'batch_id'          => $ctx['batch_id'] ?? null,
                'quiz_id'           => $ctx['quiz_id'] ?? null,
                'lecturer_id'       => $ctx['lecturer_id'] ?? null,
                'provider_id'       => $row['id'],
                'provider_snapshot' => $provider->code(),
                'model_snapshot'    => $res['model'] ?? $provider->model(),
                'attempt_number'    => $attemptNumber,
                'is_fallback'       => $i > 0,
                'status'            => $res['ok'] ? 'success' : 'failed',
                'error_category'    => $res['error_category'],
                'http_status'       => $res['http_status'],
                'latency_ms'        => $res['latency_ms'],
                'requested_count'   => $ctx['requested_count'] ?? 0,
                'received_count'    => $ctx['received_count'] ?? 0,
                'duplicate_count'   => $ctx['duplicate_count'] ?? 0,
                'token_prompt'      => $res['tokens_prompt'],
                'token_completion'  => $res['tokens_completion'],
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            if ($res['ok']) {
                $res['provider']    = $provider->code();
                $res['provider_id'] = $row['id'];

                return $res;
            }

            $last = $res;
        }

        return [
            'ok'                => false,
            'content'           => null,
            'error_category'    => $last['error_category'] ?? 'no_provider',
            'http_status'       => $last['http_status'] ?? null,
            'latency_ms'        => 0,
            'tokens_prompt'     => 0,
            'tokens_completion' => 0,
            'tokens_reasoning'  => 0,
            'model'             => null,
            'provider'          => null,
            'finish_reason'     => null,
        ];
    }

    /** Uji koneksi satu provider (admin). */
    public function testProvider(array $row): array
    {
        $provider = $this->makeProvider($row);

        return $provider === null
            ? ['ok' => false, 'error_category' => 'no_key', 'http_status' => null, 'latency_ms' => 0]
            : $provider->testConnection();
    }

    private function makeProvider(array $row): ?AIProviderInterface
    {
        $key = QuizCrypto::decrypt($row['api_key_encrypted']);
        if ($key === null || $key === '') {
            return null;
        }

        $class = match ($row['provider_code']) {
            'openrouter' => OpenRouterProvider::class,
            default      => null,
        };

        return $class === null ? null : new $class([
            'base_url' => $row['base_url'],
            'api_key'  => $key,
            'model'    => (string) $row['model_name'],
        ]);
    }
}
