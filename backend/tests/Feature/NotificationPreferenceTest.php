<?php

namespace Tests\Feature;

use App\Mail\BudgetAlertMail;
use App\Mail\FinancialReportMail;
use App\Models\Budget;
use App\Models\NotificationPreference;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_can_be_updated_with_budget_alert_and_manual_report_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/notification-preferences', [
            'budget_alerts_enabled' => true,
            'reports_enabled' => true,
            'report_frequency' => 'manual',
            'report_weekday' => 2,
            'report_month_day' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('budget_alerts_enabled', true);
        $response->assertJsonPath('report_frequency', 'manual');
    }

    public function test_send_test_report_endpoint_sends_financial_report_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notification-preferences/send-test-report');

        $response->assertOk();
        Mail::assertSent(FinancialReportMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_budget_alert_command_sends_email_when_budget_is_near_limit(): void
    {
        Mail::fake();

        $user = User::factory()->create();
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
            'month' => now()->format('Y-m'),
            'amount' => 100.00,
            'alert_threshold' => 80,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'description' => 'Lunch',
            'amount' => 90.00,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $food->id,
            'transaction_date' => now()->toDateString(),
        ]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'budget_alerts_enabled' => true,
            'reports_enabled' => true,
            'report_frequency' => 'weekly',
            'report_weekday' => (int) now()->dayOfWeekIso,
            'report_month_day' => (int) now()->day,
        ]);

        Artisan::call('emails:send-budget-alerts');

        Mail::assertSent(BudgetAlertMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertNotNull(NotificationPreference::first()->last_reminder_sent_at);
    }

    public function test_manual_reports_are_not_sent_by_the_scheduled_report_command(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'budget_alerts_enabled' => true,
            'reports_enabled' => true,
            'report_frequency' => 'manual',
            'report_weekday' => (int) now()->dayOfWeekIso,
            'report_month_day' => (int) now()->day,
        ]);

        Artisan::call('emails:send-financial-reports');

        Mail::assertNotSent(FinancialReportMail::class);
    }
}
