<?php

namespace App\Services;

use App\Models\StudentSemester;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialReportBuilder
{
    public function build(Request $request): array
    {
        [$period, $month, $selectedSemester, $start, $end, $label] = $this->resolveWindow($request);

        $transactions = $request->user()
            ->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'description', 'amount', 'transaction_type_id', 'transaction_category_id', 'transaction_date', 'created_at']);

        $items = $transactions->map(function ($transaction) {
            $type = strtolower((string) optional($transaction->transactionType)->name);

            return [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => round((float) $transaction->amount, 2),
                'transaction_type_id' => $transaction->transaction_type_id,
                'transaction_category_id' => $transaction->transaction_category_id,
                'type' => $type === 'income' ? 'income' : 'expense',
                'category' => optional($transaction->transactionCategory)->name ?: 'Uncategorized',
                'transaction_date' => optional($transaction->transaction_date)->format('Y-m-d'),
                'created_at' => optional($transaction->created_at)->toIso8601String(),
            ];
        })->values();

        $incomeItems = $items->filter(fn ($item) => $item['type'] === 'income');
        $expenseItems = $items->filter(fn ($item) => $item['type'] === 'expense');

        $income = (float) $incomeItems->sum('amount');
        $expense = (float) $expenseItems->sum('amount');
        $net = $income - $expense;
        $averageExpense = $expenseItems->count() > 0 ? $expense / $expenseItems->count() : 0;

        $expenseByCategory = $expenseItems
            ->groupBy(fn ($item) => $item['category'] ?: 'Uncategorized')
            ->map(function ($group, $category) {
                return [
                    'category' => $category,
                    'amount' => round((float) $group->sum('amount'), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        $largestExpenseCategory = $expenseByCategory->first();

        return [
            'title' => 'Financial Report',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'type' => $period,
                'label' => $label,
                'month' => $month,
                'semester' => $selectedSemester ? [
                    'id' => $selectedSemester->id,
                    'name' => $selectedSemester->name,
                    'start_date' => Carbon::parse($selectedSemester->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($selectedSemester->end_date)->format('Y-m-d'),
                ] : null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'transaction_count' => $items->count(),
                'income_count' => $incomeItems->count(),
                'expense_count' => $expenseItems->count(),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($net, 2),
                'average_expense' => round($averageExpense, 2),
                'largest_expense_category' => $largestExpenseCategory['category'] ?? null,
                'largest_expense_amount' => $largestExpenseCategory['amount'] ?? 0,
            ],
            'category_breakdown' => $expenseByCategory,
            'transactions' => $items,
        ];
    }

    public function resolveWindow(Request $request): array
    {
        $period = $this->resolvePeriod($request->query('period'), $request->user()->profile_type);
        $month = $this->resolveMonth($request->query('month'));
        $selectedSemester = $period === 'semester'
            ? $this->resolveStudentSemester($request, $month)
            : null;

        [$start, $end, $label] = $period === 'semester'
            ? $this->semesterRange($selectedSemester, $month)
            : $this->monthRange($month);

        return [$period, $month, $selectedSemester, $start, $end, $label];
    }

    private function resolveMonth(?string $input): string
    {
        if ($input !== null && !preg_match('/^\d{4}-\d{2}$/', (string) $input)) {
            throw ValidationException::withMessages([
                'month' => ['Invalid month format. Use YYYY-MM.'],
            ]);
        }

        try {
            if ($input) {
                return Carbon::createFromFormat('Y-m', $input)->format('Y-m');
            }
        } catch (\Throwable $e) {
        }

        return now()->format('Y-m');
    }

    private function resolvePeriod(?string $input, ?string $profileType): string
    {
        if ($profileType !== 'student') {
            return 'monthly';
        }

        return in_array($input, ['monthly', 'semester'], true) ? $input : 'monthly';
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end, $start->format('F Y')];
    }

    private function semesterRange(?StudentSemester $semester, string $month): array
    {
        if ($semester) {
            return [
                Carbon::parse($semester->start_date)->startOfDay(),
                Carbon::parse($semester->end_date)->endOfDay(),
                $semester->name,
            ];
        }

        return $this->monthRange($month);
    }

    private function resolveStudentSemester(Request $request, string $month): ?StudentSemester
    {
        $semesterId = (int) $request->query('semester_id');
        if ($semesterId > 0) {
            return $request->user()->studentSemesters()->whereKey($semesterId)->first();
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $matchedSemester = $request->user()->studentSemesters()
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->orderBy('start_date')
            ->first();

        if ($matchedSemester) {
            return $matchedSemester;
        }

        return $request->user()->studentSemesters()
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
    }
}
