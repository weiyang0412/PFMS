<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();
        $currentMonthEnd = $today->copy()->endOfMonth();
        $previousMonthDate = $today->copy()->subMonthNoOverflow();
        $previousMonthStart = $previousMonthDate->copy()->startOfMonth();
        $previousMonthEnd = $previousMonthDate->copy()->endOfMonth();

        $accounts = $user->accounts()->latest()->get();
        $transactions = $user->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $totalBalance = (float) $accounts->sum('balance');

        $currentMonthTransactions = $transactions->filter(function ($transaction) use ($currentMonthStart, $currentMonthEnd) {
            return $transaction->transaction_date !== null
                && $transaction->transaction_date->between($currentMonthStart, $currentMonthEnd);
        });

        $previousMonthTransactions = $transactions->filter(function ($transaction) use ($previousMonthStart, $previousMonthEnd) {
            return $transaction->transaction_date !== null
                && $transaction->transaction_date->between($previousMonthStart, $previousMonthEnd);
        });

        $monthlyIncome = $this->sumByType($currentMonthTransactions, 'income');
        $monthlyExpense = $this->sumByType($currentMonthTransactions, 'expense');
        $previousIncome = $this->sumByType($previousMonthTransactions, 'income');
        $previousExpense = $this->sumByType($previousMonthTransactions, 'expense');
        $netCashflow = $monthlyIncome - $monthlyExpense;
        $savingsRate = $monthlyIncome > 0 ? round(($netCashflow / $monthlyIncome) * 100, 1) : 0;

        $monthlyTrend = collect(range(5, 1))
            ->map(function ($monthsAgo) use ($today, $transactions) {
                $date = $today->copy()->subMonthsNoOverflow($monthsAgo);
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();

                $items = $transactions->filter(function ($transaction) use ($start, $end) {
                    return $transaction->transaction_date !== null
                        && $transaction->transaction_date->between($start, $end);
                });

                $income = $this->sumByType($items, 'income');
                $expense = $this->sumByType($items, 'expense');

                return [
                    'month' => $date->format('M'),
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'net' => round($income - $expense, 2),
                ];
            })
            ->push([
                'month' => $today->format('M'),
                'income' => round($monthlyIncome, 2),
                'expense' => round($monthlyExpense, 2),
                'net' => round($netCashflow, 2),
            ])
            ->values();

        $categoryBreakdown = $currentMonthTransactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->groupBy(fn ($transaction) => optional($transaction->transactionCategory)->name ?: 'Uncategorized')
            ->map(function ($items, $category) use ($monthlyExpense) {
                $amount = (float) $items->sum('amount');

                return [
                    'category' => $category,
                    'amount' => round($amount, 2),
                    'percentage' => $monthlyExpense > 0 ? round(($amount / $monthlyExpense) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        $recentTransactions = $transactions
            ->take(5)
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'category' => optional($transaction->transactionCategory)->name ?: 'Uncategorized',
                    'type' => strtolower((string) optional($transaction->transactionType)->name) === 'income' ? 'income' : 'expense',
                    'amount' => (float) $transaction->amount,
                    'transaction_date' => optional($transaction->transaction_date)->format('Y-m-d'),
                ];
            })
            ->values();

        $largestExpenseCategory = $categoryBreakdown->first();
        $averageExpense = $currentMonthTransactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->avg('amount');

        return response()->json([
            'overview' => [
                'total_balance' => round($totalBalance, 2),
                'account_count' => $accounts->count(),
                'monthly_income' => round($monthlyIncome, 2),
                'monthly_expense' => round($monthlyExpense, 2),
                'net_cashflow' => round($netCashflow, 2),
                'savings_rate' => $savingsRate,
                'income_change_pct' => $this->calculatePercentageChange($monthlyIncome, $previousIncome),
                'expense_change_pct' => $this->calculatePercentageChange($monthlyExpense, $previousExpense),
            ],
            'accounts' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => (float) $account->balance,
                ];
            })->values(),
            'monthly_trend' => $monthlyTrend,
            'category_breakdown' => $categoryBreakdown,
            'recent_transactions' => $recentTransactions,
            'insights' => [
                'largest_expense_category' => $largestExpenseCategory['category'] ?? null,
                'largest_expense_amount' => $largestExpenseCategory['amount'] ?? 0,
                'average_expense' => round((float) ($averageExpense ?? 0), 2),
                'transactions_this_month' => $currentMonthTransactions->count(),
            ],
            'period' => [
                'label' => $today->format('F Y'),
                'updated_at' => $today->format('Y-m-d'),
            ],
        ]);
    }

    private function sumByType($transactions, string $type): float
    {
        return (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === $type)
            ->sum('amount');
    }

    private function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
