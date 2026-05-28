<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gamification_summary_returns_points_badges_and_challenges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-31'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $incomeType = TransactionType::create(['user_id' => $user->id, 'name' => 'income']);
        $expenseType = TransactionType::create(['user_id' => $user->id, 'name' => 'expense']);
        $salaryCategory = TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'applies_to' => TransactionCategory::APPLIES_TO_INCOME,
        ]);
        $foodCategory = TransactionCategory::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'applies_to' => TransactionCategory::APPLIES_TO_EXPENSE,
        ]);

        Budget::create([
            'user_id' => $user->id,
            'transaction_category_id' => $foodCategory->id,
            'month' => '2026-05',
            'amount' => 300.00,
            'alert_threshold' => 80,
        ]);

        foreach (range(1, 5) as $day) {
            Transaction::create([
                'user_id' => $user->id,
                'description' => 'Salary ' . $day,
                'amount' => 100.00,
                'transaction_type_id' => $incomeType->id,
                'transaction_category_id' => $salaryCategory->id,
                'transaction_date' => sprintf('2026-05-%02d', $day),
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'description' => 'Lunch ' . $day,
                'amount' => 50.00,
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $foodCategory->id,
                'transaction_date' => sprintf('2026-05-%02d', $day),
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/gamification/summary?month=2026-05');

        $response->assertOk();
        $response->assertJsonPath('profile.points', 355);
        $response->assertJsonPath('profile.level', 2);
        $response->assertJsonPath('profile.transactions_count', 10);
        $response->assertJsonPath('profile.streak_days', 5);
        $response->assertJsonPath('badges.0.code', 'first_step');
        $response->assertJsonPath('badges.0.earned', true);
        $response->assertJsonPath('badges.1.code', 'momentum_maker');
        $response->assertJsonPath('badges.2.code', 'budget_builder');
        $response->assertJsonPath('badges.3.code', 'savings_spark');
        $response->assertJsonPath('badges.4.code', 'savings_hero');
        $response->assertJsonPath('challenges.0.completed', true);
        $response->assertJsonPath('points_breakdown.5.code', 'challenge_rewards');
        $response->assertJsonPath('points_breakdown.5.points', 70);
        $response->assertJsonPath('rewards.0.unlocked', true);
        $response->assertJsonPath('rewards.2.unlocked', false);

        Carbon::setTestNow();
    }
}
