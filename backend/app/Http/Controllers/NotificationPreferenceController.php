<?php

namespace App\Http\Controllers;

use App\Mail\FinancialReportMail;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preference = $this->resolvePreference($request->user()->id);
        return response()->json($preference);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'budget_alerts_enabled' => ['required', 'boolean'],
            'reports_enabled' => ['required', 'boolean'],
            'report_frequency' => ['required', Rule::in(['weekly', 'monthly', 'manual'])],
            'report_weekday' => ['required', 'integer', 'between:1,7'],
            'report_month_day' => ['required', 'integer', 'between:1,28'],
        ]);

        $preference = $this->resolvePreference($request->user()->id);
        $preference->fill($validated);
        $preference->save();

        return response()->json($preference->fresh());
    }

    public function sendTestReport(Request $request)
    {
        $user = $request->user();
        $payload = $this->buildCurrentMonthPayload($user);
        Mail::to($user->email)->send(new FinancialReportMail($payload));

        return response()->json(['message' => 'Report email sent.']);
    }

    public function sendTestBudgetAlert(Request $request)
    {
        $user = $request->user();
        $alerts = $this->buildBudgetAlertsForUser($user, now()->format('Y-m'));
        $alertLevel = $this->resolveBudgetAlertLevel($alerts);

        $payload = [
            'name' => $user->name,
            'period_label' => Carbon::now()->format('F Y'),
            'alert_level' => $alertLevel,
            'alert_count' => $alerts->count(),
            'highest_usage_pct' => (float) $alerts->max('usage_pct'),
            'highest_category' => $alerts->sortByDesc('usage_pct')->first()['category'] ?? null,
            'total_overspent' => round((float) $alerts->sum(fn ($alert) => max(0, (float) ($alert['spent'] ?? 0) - (float) ($alert['amount'] ?? 0))), 2),
            'advice' => $this->buildBudgetAdvice($alerts),
            'alerts' => $alerts->values()->all(),
        ];

        Mail::to($user->email)->send(new \App\Mail\BudgetAlertMail($payload));

        return response()->json(['message' => 'Budget alert email sent.']);
    }

    private function resolveBudgetAlertLevel($alerts): string
    {
        $highestUsage = (float) $alerts->max('usage_pct');

        return $highestUsage >= 100 ? 'urgent' : 'warning';
    }

    private function resolvePreference(int $userId): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $userId],
            [
                'budget_alerts_enabled' => true,
                'reports_enabled' => true,
                'report_frequency' => 'weekly',
                'report_weekday' => 1,
                'report_month_day' => 1,
            ]
        );
    }

    private function buildCurrentMonthPayload($user): array
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $previousStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $currentMetrics = $this->buildPeriodMetrics($user, $currentStart, $currentEnd);
        $previousMetrics = $this->buildPeriodMetrics($user, $previousStart, $previousEnd);

        $topExpenseCategory = $currentMetrics['expense_by_category']->sortDesc()->first();
        $topExpenseCategoryName = $currentMetrics['expense_by_category']->sortDesc()->keys()->first();
        $expenseChangePct = $previousMetrics['expense'] > 0
            ? (($currentMetrics['expense'] - $previousMetrics['expense']) / $previousMetrics['expense']) * 100
            : null;
        $incomeChangePct = $previousMetrics['income'] > 0
            ? (($currentMetrics['income'] - $previousMetrics['income']) / $previousMetrics['income']) * 100
            : null;

        return [
            'name' => $user->name,
            'period_label' => $now->format('F Y'),
            'current_period_label' => $currentStart->format('F Y'),
            'income' => round($currentMetrics['income'], 2),
            'expense' => round($currentMetrics['expense'], 2),
            'net' => round($currentMetrics['net'], 2),
            'savings_rate' => round($currentMetrics['savings_rate'], 1),
            'income_change_pct' => $incomeChangePct !== null ? round($incomeChangePct, 1) : null,
            'expense_change_pct' => $expenseChangePct !== null ? round($expenseChangePct, 1) : null,
            'top_expense_category' => $topExpenseCategoryName,
            'top_expense_category_amount' => round((float) $topExpenseCategory, 2),
            'insight' => $this->buildInsightMessage($currentMetrics, $expenseChangePct, $topExpenseCategoryName),
            'advice' => $this->buildAdviceMessage($currentMetrics, $expenseChangePct, $topExpenseCategoryName),
        ];
    }

    private function buildPeriodMetrics($user, Carbon $start, Carbon $end): array
    {
        $transactions = $user->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get(['amount', 'transaction_type_id', 'transaction_category_id']);

        $income = (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'income')
            ->sum('amount');
        $expense = (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->sum('amount');
        $expenseByCategory = $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->groupBy(fn ($transaction) => optional($transaction->transactionCategory)->name ?: 'Uncategorized')
            ->map(fn ($items) => (float) $items->sum('amount'));

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'savings_rate' => $income > 0 ? (($income - $expense) / $income) * 100 : 0,
            'expense_by_category' => $expenseByCategory,
        ];
    }

    private function buildInsightMessage(array $metrics, ?float $expenseChangePct, ?string $topExpenseCategoryName): string
    {
        if ($metrics['income'] <= 0 && $metrics['expense'] <= 0) {
            return 'No transactions were recorded this month yet, so your report is still waiting for data.';
        }

        if ($expenseChangePct !== null && $expenseChangePct > 0) {
            $category = $topExpenseCategoryName ?: 'your top category';
            return sprintf(
                'Spending is up by %.1f%% compared with last month, with %s leading your expenses.',
                round($expenseChangePct, 1),
                $category
            );
        }

        if ($metrics['savings_rate'] >= 20) {
            return 'You kept a healthy savings rate this month and ended in a strong position.';
        }

        if ($metrics['net'] < 0) {
            return 'Your spending exceeded your income this month, which is a useful signal to tighten the budget.';
        }

        return 'Your spending stayed under control this month, with room to improve your savings rate a little more.';
    }

    private function buildAdviceMessage(array $metrics, ?float $expenseChangePct, ?string $topExpenseCategoryName): string
    {
        if ($metrics['income'] <= 0 && $metrics['expense'] <= 0) {
            return 'Add your first transaction to unlock personalized insights and budget tracking.';
        }

        if ($metrics['net'] < 0) {
            return 'Try reviewing discretionary spending first, especially categories that do not directly support essentials.';
        }

        if ($expenseChangePct !== null && $expenseChangePct > 10) {
            $category = $topExpenseCategoryName ?: 'your highest-spend category';
            return sprintf(
                'Review %s and look for one or two recurring purchases you can trim next month.',
                $category
            );
        }

        if ($metrics['savings_rate'] >= 20) {
            return 'Consider moving part of this month’s surplus into savings or an investment goal right away.';
        }

        return 'Keep an eye on your largest category and check whether a small budget cap would help.';
    }

    private function buildBudgetAlertsForUser($user, string $month)
    {
        $budgets = $user->budgets()
            ->with('transactionCategory:id,name')
            ->where('month', $month)
            ->get();

        if ($budgets->isEmpty()) {
            return collect();
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $spentByCategory = $user->transactions()
            ->whereNotNull('transaction_category_id')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->whereHas('transactionType', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['expense']);
            })
            ->selectRaw('transaction_category_id, SUM(amount) as spent')
            ->groupBy('transaction_category_id')
            ->pluck('spent', 'transaction_category_id');

        return $budgets
            ->map(function ($budget) use ($spentByCategory) {
                $amount = (float) $budget->amount;
                if ($amount <= 0) {
                    return null;
                }

                $spent = round((float) ($spentByCategory[$budget->transaction_category_id] ?? 0), 2);
                $usagePct = round(($spent / $amount) * 100, 1);
                $threshold = (int) $budget->alert_threshold;

                if ($usagePct < $threshold) {
                    return null;
                }

                return [
                    'category' => optional($budget->transactionCategory)->name ?? 'Category',
                    'spent' => $spent,
                    'amount' => round($amount, 2),
                    'remaining' => round($amount - $spent, 2),
                    'usage_pct' => $usagePct,
                    'alert_threshold' => $threshold,
                ];
            })
            ->filter()
            ->values();
    }

    private function buildBudgetAdvice($alerts): string
    {
        $topAlert = $alerts->sortByDesc('usage_pct')->first();

        if (!$topAlert) {
            return 'Open SmartBudget to review your budgets and spending.';
        }

        $usagePct = (float) ($topAlert['usage_pct'] ?? 0);
        $category = $topAlert['category'] ?? 'this category';

        if ($usagePct >= 100) {
            return "You've exceeded the budget for {$category}. Review recent transactions and trim the next few purchases.";
        }

        if ($usagePct >= 90) {
            return "You're very close to the limit for {$category}. Consider pausing non-essential spending in this category.";
        }

        return "Keep an eye on {$category} and review upcoming expenses before they push you over the limit.";
    }
}
