<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_index_calculates_spent_remaining_and_usage_correctly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $expenseType = TransactionType::create([
            'user_id' => $user->id,
            'name' => 'expense',
        ]);

        $food = TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Food',
        ]);

        Budget::create([
            'user_id' => $user->id,
            'transaction_category_id' => $food->id,
            'month' => '2026-04',
            'amount' => 100.00,
            'alert_threshold' => 80,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'description' => 'Lunch',
            'amount' => 90.00,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $food->id,
            'transaction_date' => '2026-04-10',
        ]);

        $response = $this->getJson('/api/budgets?month=2026-04');

        $response->assertOk();
        $response->assertJsonPath('summary.total_budget', 100);
        $response->assertJsonPath('summary.total_spent', 90);
        $response->assertJsonPath('summary.warning_count', 1);
        $response->assertJsonPath('summary.total_overspent', 0);
        $response->assertJsonFragment([
            'category' => 'Food',
            'spent' => 90.0,
            'remaining' => 10.0,
            'usage_pct' => 90.0,
            'alert_level' => 'warning',
        ]);
    }

    public function test_copy_previous_month_upserts_budgets_for_target_month(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $food = TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Food',
        ]);

        $transport = TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Transport',
        ]);

        Budget::create([
            'user_id' => $user->id,
            'transaction_category_id' => $food->id,
            'month' => '2026-03',
            'amount' => 300.00,
            'alert_threshold' => 75,
        ]);

        Budget::create([
            'user_id' => $user->id,
            'transaction_category_id' => $transport->id,
            'month' => '2026-03',
            'amount' => 200.00,
            'alert_threshold' => 85,
        ]);

        $response = $this->postJson('/api/budgets/copy-previous-month', [
            'month' => '2026-04',
        ]);

        $response->assertOk();
        $response->assertJsonPath('copied_count', 2);
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $food->id,
            'month' => '2026-04',
            'alert_threshold' => 75,
        ]);
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $transport->id,
            'month' => '2026-04',
            'alert_threshold' => 85,
        ]);
    }

    public function test_budget_index_excludes_income_only_categories_without_budgets(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'applies_to' => 'income',
        ]);

        TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'applies_to' => 'expense',
        ]);

        $response = $this->getJson('/api/budgets?month=2026-04');

        $response->assertOk();
        $response->assertJsonMissing(['category' => 'Salary']);
        $response->assertJsonFragment(['category' => 'Food']);
    }
}
