<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OllamaFinancialInsightsService
{
    public function generate(array $context): ?array
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', 'llama3.2:3b');

        if ($baseUrl === '' || $model === '') {
            return null;
        }

        $timeout = (int) config('ollama.timeout', 45);
        $schema = $this->outputSchema($context, $model);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($baseUrl . '/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'format' => $schema,
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
                            'content' => $this->buildUserPrompt($context, $schema),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $content = data_get($response->json(), 'message.content');
            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = json_decode($content, true);
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
            'Return JSON only. No markdown, no code fences, no commentary.',
            'Keep the analysis concise, practical, and grounded in the numbers.',
            'If a field is unknown, use null or an empty array.',
        ]);
    }

    private function buildUserPrompt(array $context, array $schema): string
    {
        return json_encode([
            'task' => 'Analyze the user financial snapshot and produce insights and short-term predictions.',
            'output_schema' => $schema,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function outputSchema(array $context, string $model): array
    {
        $fallback = $this->buildFallback($context, $model);

        return [
            'type' => 'object',
            'properties' => [
                'source' => ['type' => 'string'],
                'model' => ['type' => 'string'],
                'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'confidence' => ['type' => 'integer'],
                'summary' => ['type' => 'string'],
                'signals' => [
                    'type' => 'object',
                    'properties' => [
                        'income_trend' => ['type' => 'string', 'enum' => ['up', 'down', 'stable']],
                        'expense_trend' => ['type' => 'string', 'enum' => ['up', 'down', 'stable']],
                        'largest_expense_category' => ['type' => ['string', 'null']],
                        'largest_expense_amount' => ['type' => 'number'],
                        'largest_expense_share' => ['type' => 'number'],
                        'transaction_count' => ['type' => 'integer'],
                        'savings_rate' => ['type' => 'number'],
                    ],
                ],
                'forecast' => [
                    'type' => 'object',
                    'properties' => [
                        'next_month' => $this->forecastPointSchema($fallback['forecast']['next_month']),
                        'next_three_months' => [
                            'type' => 'array',
                            'items' => $this->forecastPointSchema($fallback['forecast']['next_month']),
                        ],
                    ],
                ],
                'recommendations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'detail' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        ],
                    ],
                ],
            ],
            'required' => ['source', 'model', 'risk_level', 'confidence', 'summary', 'signals', 'forecast', 'recommendations'],
        ];
    }

    private function forecastPointSchema(array $fallback): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string'],
                'month_key' => ['type' => 'string'],
                'income' => ['type' => 'number'],
                'expense' => ['type' => 'number'],
                'net' => ['type' => 'number'],
            ],
            'required' => ['month', 'month_key', 'income', 'expense', 'net'],
        ];
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

        return $normalized;
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
}
