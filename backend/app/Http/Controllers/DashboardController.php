<?php

namespace App\Http\Controllers;

use App\Models\StudentSemester;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $periodType = $this->resolvePeriodType($request->query('period'), $user->profile_type);
        $anchorMonth = $this->resolveAnchorMonth($request->query('month'));
        $selectedSemester = $periodType === 'semester'
            ? $this->resolveStudentSemester($request, $anchorMonth)
            : null;

        [$currentStart, $currentEnd, $periodLabel] = $periodType === 'semester'
            ? $this->semesterRange($selectedSemester, $anchorMonth)
            : $this->monthRange($anchorMonth);
        [$previousStart, $previousEnd] = $periodType === 'semester'
            ? $this->previousSemesterRange($currentStart, $currentEnd)
            : $this->previousMonthRange($anchorMonth);

        $accounts = $user->accounts()->latest()->get();
        $transactions = $user->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $totalBalance = (float) $accounts->sum('balance');

        $currentPeriodTransactions = $transactions->filter(function ($transaction) use ($currentStart, $currentEnd) {
            return $transaction->transaction_date !== null
                && $transaction->transaction_date->between($currentStart, $currentEnd);
        });

        $previousPeriodTransactions = $transactions->filter(function ($transaction) use ($previousStart, $previousEnd) {
            return $transaction->transaction_date !== null
                && $transaction->transaction_date->between($previousStart, $previousEnd);
        });

        $periodIncome = $this->sumByType($currentPeriodTransactions, 'income');
        $periodExpense = $this->sumByType($currentPeriodTransactions, 'expense');
        $previousIncome = $this->sumByType($previousPeriodTransactions, 'income');
        $previousExpense = $this->sumByType($previousPeriodTransactions, 'expense');
        $netCashflow = $periodIncome - $periodExpense;
        $savingsRate = $periodIncome > 0 ? round(($netCashflow / $periodIncome) * 100, 1) : 0;

        $trendMonths = $periodType === 'semester'
            ? $this->monthsInRange($currentStart, $currentEnd)
            : collect(range(5, 0))->map(fn ($monthsAgo) => $anchorMonth->copy()->subMonthsNoOverflow($monthsAgo))->values();

        $monthlyTrend = $trendMonths
            ->map(function (Carbon $date) use ($transactions) {
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
                    'month_key' => $date->format('Y-m'),
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'net' => round($income - $expense, 2),
                ];
            })
            ->values();

        $categoryBreakdown = $currentPeriodTransactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->groupBy(fn ($transaction) => optional($transaction->transactionCategory)->name ?: 'Uncategorized')
            ->map(function ($items, $category) use ($periodExpense) {
                $amount = (float) $items->sum('amount');

                return [
                    'category' => $category,
                    'amount' => round($amount, 2),
                    'percentage' => $periodExpense > 0 ? round(($amount / $periodExpense) * 100, 1) : 0,
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
        $averageExpense = $currentPeriodTransactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->avg('amount');

        return response()->json([
            'overview' => [
                'total_balance' => round($totalBalance, 2),
                'account_count' => $accounts->count(),
                'monthly_income' => round($periodIncome, 2),
                'monthly_expense' => round($periodExpense, 2),
                'net_cashflow' => round($netCashflow, 2),
                'savings_rate' => $savingsRate,
                'income_change_pct' => $this->calculatePercentageChange($periodIncome, $previousIncome),
                'expense_change_pct' => $this->calculatePercentageChange($periodExpense, $previousExpense),
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
                'transactions_this_month' => $currentPeriodTransactions->count(),
            ],
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'comparison_label' => $periodType === 'semester' ? 'vs previous semester' : 'vs last month',
                'updated_at' => $today->format('Y-m-d'),
                'month' => $anchorMonth->format('Y-m'),
                'semester_id' => $selectedSemester ? $selectedSemester->id : null,
            ],
        ]);
    }

    private function resolvePeriodType(?string $input, ?string $profileType): string
    {
        if ($profileType !== 'student') {
            return 'monthly';
        }
        return in_array($input, ['monthly', 'semester'], true) ? $input : 'monthly';
    }

    private function resolveAnchorMonth(?string $input): Carbon
    {
        try {
            if ($input && preg_match('/^\d{4}-\d{2}$/', $input)) {
                return Carbon::createFromFormat('Y-m', $input)->startOfMonth();
            }
        } catch (\Throwable $e) {
        }

        return Carbon::today()->startOfMonth();
    }

    private function monthRange(Carbon $anchorMonth): array
    {
        $start = $anchorMonth->copy()->startOfMonth();
        $end = $anchorMonth->copy()->endOfMonth();
        return [$start, $end, $start->format('F Y')];
    }

    private function previousMonthRange(Carbon $anchorMonth): array
    {
        $previous = $anchorMonth->copy()->subMonthNoOverflow();
        return [$previous->copy()->startOfMonth(), $previous->copy()->endOfMonth()];
    }

    private function semesterRange(?StudentSemester $semester, Carbon $anchorMonth): array
    {
        if ($semester) {
            $start = Carbon::parse($semester->start_date)->startOfDay();
            $end = Carbon::parse($semester->end_date)->endOfDay();
            return [$start, $end, $semester->name];
        }

        $start = $anchorMonth->copy()->startOfMonth();
        $end = $anchorMonth->copy()->endOfMonth();

        return [$start, $end, $start->format('F Y')];
    }

    private function previousSemesterRange(Carbon $currentStart, Carbon $currentEnd): array
    {
        $durationDays = max(1, (int) $currentStart->diffInDays($currentEnd) + 1);
        $end = $currentStart->copy()->subDay()->endOfDay();
        $start = $end->copy()->subDays($durationDays - 1)->startOfDay();
        return [$start, $end];
    }

    private function resolveStudentSemester(Request $request, Carbon $anchorMonth): ?StudentSemester
    {
        $semesterId = (int) $request->query('semester_id');
        if ($semesterId > 0) {
            return $request->user()->studentSemesters()->whereKey($semesterId)->first();
        }

        $monthStart = $anchorMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $anchorMonth->copy()->endOfMonth()->toDateString();
        $active = $request->user()->studentSemesters()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->orderBy('start_date')
            ->first();

        if ($active) {
            return $active;
        }

        return $request->user()->studentSemesters()
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
    }

    private function monthsInRange(Carbon $start, Carbon $end)
    {
        $months = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $months->push($cursor->copy());
            $cursor->addMonthNoOverflow();
        }

        return $months;
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
