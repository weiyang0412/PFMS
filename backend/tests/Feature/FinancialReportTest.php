<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_report_index_returns_summary_and_transactions(): void
    {
        $user = User::factory()->create();
        $type = TransactionType::create(['user_id' => $user->id, 'name' => 'income']);
        $category = TransactionCategory::create(['user_id' => $user->id, 'name' => 'Salary', 'applies_to' => TransactionCategory::APPLIES_TO_INCOME]);

        Transaction::create([
            'user_id' => $user->id,
            'description' => 'May salary',
            'amount' => 2500,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/financial');

        $response->assertOk()
            ->assertJsonPath('summary.transaction_count', 1)
            ->assertJsonPath('summary.income', 2500)
            ->assertJsonPath('transactions.0.description', 'May salary');
    }

    public function test_financial_report_can_export_pdf_and_excel(): void
    {
        $user = User::factory()->create();
        $type = TransactionType::create(['user_id' => $user->id, 'name' => 'expense']);
        $category = TransactionCategory::create(['user_id' => $user->id, 'name' => 'Groceries', 'applies_to' => TransactionCategory::APPLIES_TO_EXPENSE]);

        Transaction::create([
            'user_id' => $user->id,
            'description' => 'Market run',
            'amount' => 45.8,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $pdfResponse = $this->actingAs($user, 'sanctum')->get('/api/reports/financial/export?format=pdf');
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));

        $xlsxResponse = $this->actingAs($user, 'sanctum')->get('/api/reports/financial/export?format=excel');
        $xlsxResponse->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsxResponse->headers->get('Content-Type'));
    }
}
