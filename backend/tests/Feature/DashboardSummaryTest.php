<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\OllamaFinancialInsightsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_ai_insights_and_forecasts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->mock(OllamaFinancialInsightsService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'source' => 'ollama',
                'model' => 'llama3.2:3b',
                'risk_level' => 'medium',
                'confidence' => 88,
                'summary' => 'Local AI review shows spending is stable but food remains the main pressure point.',
                'signals' => [
                    'income_trend' => 'up',
                    'expense_trend' => 'stable',
                    'largest_expense_category' => 'Food',
                    'largest_expense_amount' => 340,
                    'largest_expense_share' => 45.3,
                    'transaction_count' => 6,
                    'savings_rate' => 18.4,
                ],
                'forecast' => [
                    'next_month' => [
                        'month' => 'Jun 2026',
                        'month_key' => '2026-06',
                        'income' => 1300,
                        'expense' => 360,
                        'net' => 940,
                    ],
                    'next_three_months' => [
                        [
                            'month' => 'Jun 2026',
                            'month_key' => '2026-06',
                            'income' => 1300,
                            'expense' => 360,
                            'net' => 940,
                        ],
                    ],
                ],
                'recommendations' => [
                    [
                        'title' => 'Watch Food spending',
                        'detail' => 'Food is still taking the biggest share of the budget.',
                        'priority' => 'high',
                    ],
                ],
            ]);
        });

        $incomeType = TransactionType::create(['user_id' => $user->id, 'name' => 'income']);
        $expenseType = TransactionType::create(['user_id' => $user->id, 'name' => 'expense']);
        $salaryCategory = TransactionCategory::create(['user_id' => $user->id, 'name' => 'Salary', 'applies_to' => TransactionCategory::APPLIES_TO_INCOME]);
        $foodCategory = TransactionCategory::create(['user_id' => $user->id, 'name' => 'Food', 'applies_to' => TransactionCategory::APPLIES_TO_EXPENSE]);

        foreach ([
            ['2026-03-15', 1000, 300],
            ['2026-04-15', 1100, 320],
            ['2026-05-15', 1200, 340],
        ] as [$date, $income, $expense]) {
            Transaction::create([
                'user_id' => $user->id,
                'description' => 'Salary payment',
                'amount' => $income,
                'transaction_type_id' => $incomeType->id,
                'transaction_category_id' => $salaryCategory->id,
                'transaction_date' => $date,
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'description' => 'Groceries',
                'amount' => $expense,
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $foodCategory->id,
                'transaction_date' => $date,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('ai_insights.source', 'ollama');
        $response->assertJsonPath('ai_insights.model', 'llama3.2:3b');
        $response->assertJsonPath('ai_insights.risk_level', 'medium');
        $response->assertJsonPath('ai_insights.confidence', 88);
        $response->assertJsonPath('ai_insights.forecast.next_month.month', 'Jun 2026');
        $response->assertJsonPath('ai_insights.recommendations.0.title', 'Watch Food spending');

        Carbon::setTestNow();
    }

    public function test_dashboard_summary_falls_back_to_empty_ai_insights(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config()->set('ollama.base_url', '');
        config()->set('ollama.model', '');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('ai_insights.source', 'ollama');
        $response->assertJsonPath('ai_insights.confidence', 0);
        $response->assertJsonPath('ai_insights.recommendations.0.title', 'Connect Ollama to enable AI insights');

        Carbon::setTestNow();
    }
}
