<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OllamaFinancialInsightsService
{
    public function warmUp(): bool
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', 'llama3.2:3b');

        if ($baseUrl === '' || $model === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post($baseUrl . '/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'keep_alive' => '10m',
                    'options' => [
                        'temperature' => 0,
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Warm up the local model. Reply with a single short confirmation.',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'OK',
                        ],
                    ],
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    public function healthCheck(): array
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', 'llama3.2:3b');

        if ($baseUrl === '' || $model === '') {
            return [
                'ok' => false,
                'reason' => 'missing_config',
                'base_url' => $baseUrl,
                'model' => $model,
            ];
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post($baseUrl . '/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Reply with OK only.',
                        ],
                    ],
                ]);

            return [
                'ok' => $response->successful(),
                'reason' => $response->successful() ? 'ok' : 'http_' . $response->status(),
                'base_url' => $baseUrl,
                'model' => $model,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'reason' => class_basename($e),
                'base_url' => $baseUrl,
                'model' => $model,
            ];
        }
    }

    public function generate(array $context): ?array
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', 'llama3.2:3b');

        if ($baseUrl === '' || $model === '') {
            return null;
        }

        $timeout = (int) config('ollama.timeout', 45);

        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'options' => [
                    'temperature' => 0.2,
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildUserPrompt($context),
                    ],
                ],
            ];

            $responseJson = $this->postJson($baseUrl . '/api/chat', $payload, $timeout);
            if (! is_array($responseJson)) {
                return null;
            }

            $content = data_get($responseJson, 'message.content');
            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = $this->decodeModelJson($content);
            if (! is_array($decoded)) {
                return null;
            }

            return $this->normalize($decoded, $context, $model);
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are a local AI financial analyst for a personal finance app.',
            'Use only the supplied data. Do not invent external facts.',
            'Use RM for all currency values. Do not use $ or other currency symbols.',
            'Return JSON only. No markdown, no code fences, no commentary.',
            'Keep the analysis concise, practical, and grounded in the numbers.',
            'If a field is unknown, use null or an empty array.',
        ]);
    }

    private function buildUserPrompt(array $context): string
    {
        return json_encode([
            'task' => 'Analyze the user financial snapshot and produce insights and short-term predictions.',
            'response_requirements' => [
                'Return JSON only.',
                'Use these top-level keys: source, model, risk_level, confidence, summary, signals, forecast, recommendations.',
                'Keep recommendations practical and concise.',
            ],
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function normalize(array $decoded, array $context, string $model): array
    {
        $fallback = $this->buildFallback($context, $model);
        $normalized = array_replace_recursive($fallback, $decoded);

        $normalized['source'] = 'ollama';
        $normalized['model'] = $model;
        $normalized['risk_level'] = in_array($normalized['risk_level'] ?? '', ['low', 'medium', 'high'], true)
            ? $normalized['risk_level']
            : $fallback['risk_level'];
        $normalized['confidence'] = max(0, min(100, (int) ($normalized['confidence'] ?? $fallback['confidence'])));
        $normalized['summary'] = trim((string) ($normalized['summary'] ?? $fallback['summary'])) ?: $fallback['summary'];
        $normalized['signals'] = $this->normalizeSignals((array) ($normalized['signals'] ?? []), $fallback['signals']);
        $normalized['forecast'] = $this->normalizeForecast((array) ($normalized['forecast'] ?? []), $fallback['forecast']);
        $normalized['recommendations'] = $this->normalizeRecommendations((array) ($normalized['recommendations'] ?? []), $fallback['recommendations']);

        return $this->sanitizeAiResponse($normalized);
    }

    private function buildFallback(array $context, string $model): array
    {
        return [
            'source' => 'ollama',
            'model' => $model,
            'risk_level' => 'low',
            'confidence' => 0,
            'summary' => 'Local AI insights are temporarily unavailable.',
            'signals' => [
                'income_trend' => 'stable',
                'expense_trend' => 'stable',
                'largest_expense_category' => null,
                'largest_expense_amount' => 0,
                'largest_expense_share' => 0,
                'transaction_count' => (int) data_get($context, 'signals.transaction_count', 0),
                'savings_rate' => 0,
            ],
            'forecast' => [
                'next_month' => [
                    'month' => (string) data_get($context, 'period.next_month_label', 'Next month'),
                    'month_key' => (string) data_get($context, 'period.next_month_key', ''),
                    'income' => 0,
                    'expense' => 0,
                    'net' => 0,
                ],
                'next_three_months' => [],
            ],
            'recommendations' => [],
        ];
    }

    private function normalizeSignals(array $signals, array $fallback): array
    {
        return [
            'income_trend' => in_array($signals['income_trend'] ?? '', ['up', 'down', 'stable'], true) ? $signals['income_trend'] : $fallback['income_trend'],
            'expense_trend' => in_array($signals['expense_trend'] ?? '', ['up', 'down', 'stable'], true) ? $signals['expense_trend'] : $fallback['expense_trend'],
            'largest_expense_category' => array_key_exists('largest_expense_category', $signals) ? ($signals['largest_expense_category'] === null ? null : (string) $signals['largest_expense_category']) : $fallback['largest_expense_category'],
            'largest_expense_amount' => round((float) ($signals['largest_expense_amount'] ?? $fallback['largest_expense_amount']), 2),
            'largest_expense_share' => round((float) ($signals['largest_expense_share'] ?? $fallback['largest_expense_share']), 1),
            'transaction_count' => (int) ($signals['transaction_count'] ?? $fallback['transaction_count']),
            'savings_rate' => round((float) ($signals['savings_rate'] ?? $fallback['savings_rate']), 1),
        ];
    }

    private function normalizeForecast(array $forecast, array $fallback): array
    {
        $fallbackForecast = data_get($fallback, 'forecast', []);
        $fallbackNextMonth = (array) data_get($fallbackForecast, 'next_month', [
            'month' => (string) data_get($fallbackForecast, 'next_month.month', 'Next month'),
            'month_key' => (string) data_get($fallbackForecast, 'next_month.month_key', ''),
            'income' => 0,
            'expense' => 0,
            'net' => 0,
        ]);
        $nextMonth = $this->normalizeForecastPoint((array) ($forecast['next_month'] ?? []), $fallbackNextMonth);
        $nextThreeMonths = array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? $this->normalizeForecastPoint($item, $nextMonth) : null,
            (array) ($forecast['next_three_months'] ?? [])
        )));

        return [
            'next_month' => $nextMonth,
            'next_three_months' => $nextThreeMonths,
        ];
    }

    private function normalizeForecastPoint(array $point, array $fallback): array
    {
        return [
            'month' => trim((string) ($point['month'] ?? $fallback['month'])) ?: $fallback['month'],
            'month_key' => trim((string) ($point['month_key'] ?? $fallback['month_key'])),
            'income' => round((float) ($point['income'] ?? $fallback['income']), 2),
            'expense' => round((float) ($point['expense'] ?? $fallback['expense']), 2),
            'net' => round((float) ($point['net'] ?? $fallback['net']), 2),
        ];
    }

    private function normalizeRecommendations(array $recommendations, array $fallback): array
    {
        $items = [];
        foreach (array_slice($recommendations, 0, 4) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $priority = in_array($item['priority'] ?? '', ['low', 'medium', 'high'], true)
                ? $item['priority']
                : 'low';

            $title = trim((string) ($item['title'] ?? ''));
            $detail = trim((string) ($item['detail'] ?? ''));
            if ($title === '' || $detail === '') {
                continue;
            }

            $items[] = [
                'title' => Str::limit($title, 80, ''),
                'detail' => Str::limit($detail, 240, ''),
                'priority' => $priority,
            ];
        }

        return $items !== [] ? $items : $fallback;
    }

    private function sanitizeAiResponse($value)
    {
        if (is_string($value)) {
            return $this->sanitizeCurrencyText($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sanitizeAiResponse($item);
        }

        return $value;
    }

    private function sanitizeCurrencyText(string $value): string
    {
        $value = str_replace(['US$', '$'], 'RM ', $value);

        return preg_replace('/\s+/', ' ', trim($value)) ?? $value;
    }

    private function postJson(string $url, array $payload, int $timeout): ?array
    {
        if (! function_exists('curl_init')) {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url, $payload);

            return $response->successful() ? $response->json() : null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            report(new \RuntimeException(curl_error($ch) ?: 'Unknown cURL error'));
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function decodeModelJson(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $decoded = json_decode(trim($content), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
