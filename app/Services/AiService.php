<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiService
{
    private const ALLOWED_SENTIMENTS = ['positive', 'neutral', 'negative'];

    private const ALLOWED_CATEGORIES = ['question', 'cooperation', 'order', 'complaint', 'other'];

    private readonly ?string $apiKey;

    public function __construct(
        ?string $apiKey = null,
        private readonly string $model = 'claude-3-5-haiku-20241022',
        private readonly int $timeout = 10,
    ) {
        $this->apiKey = $apiKey ?? config('services.anthropic.api_key');
    }

    /**
     * Анализирует комментарий из заявки: тональность, категория обращения, короткий черновик ответа.
     * При любой ошибке (нет ключа, недоступен API, некорректный ответ) — возвращает fallback-результат,
     * не прерывая основной сценарий обработки заявки.
     */
    public function analyzeComment(string $comment): array
    {
        if (empty($this->apiKey)) {
            return $this->fallback('AI отключён: не задан ANTHROPIC_API_KEY.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 400,
                    'messages' => [
                        ['role' => 'user', 'content' => $this->buildPrompt($comment)],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('AiService: Anthropic API вернул ошибку', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fallback('Anthropic API вернул ошибку HTTP '.$response->status().'.');
            }

            $text = $response->json('content.0.text');

            return $this->parseModelResponse($text);
        } catch (Throwable $e) {
            Log::warning('AiService: исключение при обращении к Anthropic API', [
                'message' => $e->getMessage(),
            ]);

            return $this->fallback('Исключение при обращении к AI: '.$e->getMessage());
        }
    }

    private function buildPrompt(string $comment): string
    {
        return <<<PROMPT
            Ты — ассистент, который помогает обрабатывать заявки с формы обратной связи на лендинге разработчика.
            Проанализируй комментарий пользователя и верни ТОЛЬКО валидный JSON без пояснений и markdown, строго такой структуры:

            {"sentiment": "positive|neutral|negative", "category": "question|cooperation|order|complaint|other", "suggested_reply": "короткий вежливый черновик ответа на русском языке, 1-3 предложения"}

            Комментарий пользователя:
            "{$comment}"
            PROMPT;
    }

    private function parseModelResponse(?string $text): array
    {
        if (! $text) {
            return $this->fallback('Пустой ответ от AI.');
        }

        $json = $this->extractJson($text);
        $decoded = $json !== null ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            Log::warning('AiService: не удалось разобрать JSON из ответа модели', ['raw' => $text]);

            return $this->fallback('Не удалось разобрать ответ AI.');
        }

        $sentiment = in_array($decoded['sentiment'] ?? null, self::ALLOWED_SENTIMENTS, true)
            ? $decoded['sentiment']
            : 'neutral';

        $category = in_array($decoded['category'] ?? null, self::ALLOWED_CATEGORIES, true)
            ? $decoded['category']
            : 'other';

        return [
            'used' => true,
            'sentiment' => $sentiment,
            'category' => $category,
            'suggested_reply' => is_string($decoded['suggested_reply'] ?? null)
                ? mb_substr($decoded['suggested_reply'], 0, 1000)
                : null,
            'error' => null,
        ];
    }

    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }

    private function fallback(string $reason): array
    {
        return [
            'used' => false,
            'sentiment' => null,
            'category' => null,
            'suggested_reply' => null,
            'error' => $reason,
        ];
    }
}
