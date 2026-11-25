<?php

namespace App\Libraries\Quiz;

interface AIProviderInterface
{
    /**
     * @param array{base_url:string,api_key:string,model:string} $config
     */
    public function __construct(array $config);

    /**
     * Kirim prompt dan minta respons JSON.
     *
     * @param array{max_tokens?:int,timeout?:int,temperature?:float,reasoning?:bool|null} $opts
     *        reasoning: false = minta provider mematikan penalaran tersembunyi (hemat token
     *        output), true = aktifkan, null = jangan kirim parameter (perilaku default model).
     *
     * @return array{
     *  ok:bool, content:?string, error_category:?string,
     *  http_status:?int, latency_ms:int,
     *  tokens_prompt:int, tokens_completion:int, tokens_reasoning:int, model:?string,
     *  finish_reason:?string
     * }
     */
    public function chatJson(string $systemPrompt, string $userPrompt, int $timeoutSec = 120, array $opts = []): array;

    public function code(): string;

    public function model(): string;

    /** Uji koneksi sederhana. */
    public function testConnection(): array;
}
