<?php

namespace App\Libraries\Quiz;

/**
 * Adapter OpenRouter (kontrak OpenAI-compatible chat completions).
 */
class OpenRouterProvider implements AIProviderInterface
{
    /** Batas atas token output per request. */
    private const MAX_TOKENS_CAP = 8000;

    /**
     * Kategori kegagalan yang cepat dan transien (respons HTTP 200 kosong / bukan
     * JSON dari salah satu upstream OpenRouter). Dicoba ulang sekali; timeout
     * TIDAK diulang di sini karena mahal — penanganannya di putaran service.
     */
    private const RETRYABLE = ['empty_content', 'invalid_response'];

    /** Anggaran output minimum saat dicoba ulang karena penalaran memakan seluruh max_tokens. */
    private const REASONING_HEADROOM_TOKENS = 2500;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function code(): string
    {
        return 'openrouter';
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    public function chatJson(string $systemPrompt, string $userPrompt, int $timeoutSec = 120, array $opts = []): array
    {
        $res = $this->attempt($systemPrompt, $userPrompt, $timeoutSec, $opts);

        // HTTP 400 yang menyebut max_tokens / context length: budget output
        // (default 8000) melebihi batas model di production. Ulangi sekali
        // dengan anggaran yang lebih konservatif.
        if (
            ! $res['ok']
            && $res['error_category'] === 'http_error'
            && preg_match('/max[_ -]?tokens|context length|too large/i', (string) ($res['error_message'] ?? ''))
        ) {
            usleep(300000);
            $opts['max_tokens'] = 4096;
            $retry = $this->attempt($systemPrompt, $userPrompt, $timeoutSec, $opts);
            if ($retry['ok']) {
                $retry['latency_ms'] += $res['latency_ms'];

                return $retry;
            }
            $retry['latency_ms'] += $res['latency_ms'];

            return $retry;
        }

        if ($res['ok'] || ! in_array($res['error_category'], self::RETRYABLE, true)) {
            return $res;
        }

        usleep(300000);

        // Upstream yang tetap berpenalaran walau diminta mematikannya dapat
        // menghabiskan seluruh max_tokens sebelum JSON keluar (finish_reason =
        // "length", konten kosong): ulangi dengan anggaran output yang lebih longgar.
        $requested = (int) ($opts['max_tokens'] ?? self::MAX_TOKENS_CAP);
        if ($res['error_category'] === 'empty_content' && $res['finish_reason'] === 'length') {
            $opts['max_tokens'] = min(self::MAX_TOKENS_CAP, max(self::REASONING_HEADROOM_TOKENS, $requested * 4));
        }

        $retry = $this->attempt($systemPrompt, $userPrompt, $timeoutSec, $opts);

        if ($retry['ok']) {
            $retry['latency_ms'] += $res['latency_ms'];
        }

        return $retry;
    }

    public function testConnection(): array
    {
        $res = $this->attempt('You are a health check. Reply with the exact JSON {"ok":true}.', 'ping', 30, ['max_tokens' => 64]);

        return [
            'ok'            => $res['ok'],
            'error_category'=> $res['error_category'],
            'http_status'   => $res['http_status'],
            'latency_ms'    => $res['latency_ms'],
        ];
    }

    protected function attempt(string $systemPrompt, string $userPrompt, int $timeoutSec, array $opts = []): array
    {
        $start  = microtime(true);
        $url    = rtrim($this->config['base_url'], '/') . '/chat/completions';

        $maxTokens = max(64, min(self::MAX_TOKENS_CAP, (int) ($opts['max_tokens'] ?? self::MAX_TOKENS_CAP)));

        $body = [
            'model'       => $this->config['model'],
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => (float) ($opts['temperature'] ?? 0.4),
            'max_tokens'  => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];

        // Model "reasoning" menghabiskan ribuan token output untuk penalaran
        // tersembunyi sebelum JSON keluar (lambat, mahal, rawan terpotong).
        // Penalaran dimatikan agar output hanya JSON. env quiz.disableReasoning=false
        // membuat parameter ini tidak dikirim (perilaku default model).
        // $opts['reasoning']: false = matikan, true = nyalakan, null = jangan kirim.
        $reasoning = array_key_exists('reasoning', $opts)
            ? $opts['reasoning']
            : (self::disableReasoning() ? false : null);

        if ($reasoning !== null) {
            $body['reasoning'] = ['enabled' => (bool) $reasoning];
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => max(5, $timeoutSec),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['api_key'],
                'HTTP-Referer: ' . rtrim(config('App')->baseURL, '/'),
                'X-Title: KelasMerdeka-Quiz',
            ],
        ]);
        $raw       = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($raw === false) {
            return $this->fail($curlErrNo === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection', $httpCode ?: null, $latency);
        }

        $json = json_decode((string) $raw, true);

        // Pesan error asli dari upstream (penting untuk diagnosis di production:
        // mis. "max_tokens is too large", "model not found", dsb).
        $errMsg = '';
        if (is_array($json)) {
            $err    = $json['error'] ?? null;
            $errMsg = is_array($err) ? (string) ($err['message'] ?? '') : (string) $err;
            $errMsg = mb_substr(trim($errMsg), 0, 300);
        }

        if (! is_array($json)) {
            return $this->fail('invalid_response', $httpCode ?: null, $latency);
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return $this->fail('auth', $httpCode, $latency, $errMsg);
        }
        if ($httpCode === 429) {
            return $this->fail('rate_limit', $httpCode, $latency, $errMsg);
        }
        if ($httpCode >= 500) {
            return $this->fail('http_5xx', $httpCode, $latency, $errMsg);
        }
        if ($httpCode !== 200) {
            return $this->fail('http_error', $httpCode, $latency, $errMsg);
        }

        $message = $json['choices'][0]['message'] ?? null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;
        if (! is_string($content) || trim($content) === '') {
            $failed = $this->fail('empty_content', $httpCode, $latency);
            $failed['finish_reason']    = isset($json['choices'][0]['finish_reason']) ? (string) $json['choices'][0]['finish_reason'] : null;
            $failed['tokens_reasoning'] = (int) ($json['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0);

            return $failed;
        }

        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];
        $details = is_array($usage['completion_tokens_details'] ?? null) ? $usage['completion_tokens_details'] : [];

        return [
            'ok'                 => true,
            'content'            => $content,
            'error_category'     => null,
            'http_status'        => $httpCode,
            'latency_ms'         => $latency,
            'tokens_prompt'      => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_completion'  => (int) ($usage['completion_tokens'] ?? 0),
            'tokens_reasoning'   => (int) ($details['reasoning_tokens'] ?? 0),
            'model'              => (string) ($json['model'] ?? $this->config['model']),
            'finish_reason'      => isset($json['choices'][0]['finish_reason']) ? (string) $json['choices'][0]['finish_reason'] : null,
        ];
    }

    /** true (default) = minta penalaran dimatikan; false = kirim perilaku default model. */
    private static function disableReasoning(): bool
    {
        $value = env('quiz.disableReasoning');

        if ($value === null || $value === '') {
            return true;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }

    protected function fail(string $category, ?int $http, int $latency, string $errorMessage = ''): array
    {
        return [
            'ok' => false, 'content' => null, 'error_category' => $category,
            'error_message' => $errorMessage,
            'http_status' => $http, 'latency_ms' => $latency,
            'tokens_prompt' => 0, 'tokens_completion' => 0, 'tokens_reasoning' => 0,
            'model' => $this->config['model'], 'finish_reason' => null,
        ];
    }
}
