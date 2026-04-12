<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));
        [$monthStart, $monthEnd] = $this->monthRange($month);
        $onlyBudgeted = $request->boolean('only_budgeted');
        $defaultThreshold = $this->resolveThreshold($request->query('default_threshold'));

        $budgets = $request->user()
            ->budgets()
            ->where('month', $month)
            ->get()
            ->keyBy('transaction_category_id');

        $spentByCategory = $request->user()
            ->transactions()
            ->whereNotNull('transaction_category_id')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->whereHas('transactionType', fn ($query) => $query->where('name', TransactionType::TYPE_EXPENSE))
            ->selectRaw('transaction_category_id, SUM(amount) as spent')
            ->groupBy('transaction_category_id')
            ->pluck('spent', 'transaction_category_id');

        $categoryQuery = $request->user()
            ->transactionCategories()
            ->orderBy('name');

        if ($onlyBudgeted) {
            $categoryQuery->whereIn('id', $budgets->keys()->all());
        } else {
            $budgetedCategoryIds = $budgets->keys()->all();
            $categoryQuery->where(function ($query) use ($budgetedCategoryIds) {
                $query
                    ->whereIn('applies_to', [
                        TransactionCategory::APPLIES_TO_EXPENSE,
                        TransactionCategory::APPLIES_TO_BOTH,
                    ])
                    ->orWhereNull('applies_to');
                if (!empty($budgetedCategoryIds)) {
                    $query->orWhereIn('id', $budgetedCategoryIds);
                }
            });
        }

        $items = $categoryQuery
            ->get()
            ->map(function ($category) use ($budgets, $spentByCategory, $defaultThreshold) {
                $budget = $budgets->get($category->id);
                $spent = round((float) ($spentByCategory[$category->id] ?? 0), 2);
                $amount = $budget ? round((float) $budget->amount, 2) : null;
                $threshold = $budget ? (int) $budget->alert_threshold : $defaultThreshold;
                $usagePct = $amount && $amount > 0 ? round(($spent / $amount) * 100, 1) : 0;

                return [
                    'category_id' => $category->id,
                    'category' => $category->name,
                    'budget_id' => $budget ? $budget->id : null,
                    'amount' => $amount,
                    'alert_threshold' => $threshold,
                    'spent' => $spent,
                    'remaining' => $amount !== null ? round($amount - $spent, 2) : null,
                    'usage_pct' => $usagePct,
                    'alert_level' => $amount === null
                        ? 'none'
                        : ($usagePct >= 100 ? 'over' : ($usagePct >= $threshold ? 'warning' : 'safe')),
                ];
            })
            ->values();

        $totalOverspent = round(
            $items
                ->filter(fn ($item) => $item['remaining'] !== null && $item['remaining'] < 0)
                ->sum(fn ($item) => abs((float) $item['remaining'])),
            2
        );

        return response()->json([
            'month' => $month,
            'summary' => [
                'total_budget' => round($items->sum(fn ($item) => (float) ($item['amount'] ?? 0)), 2),
                'total_spent' => round($items->sum(fn ($item) => (float) $item['spent']), 2),
                'warning_count' => $items->whereIn('alert_level', ['warning', 'over'])->count(),
                'total_overspent' => $totalOverspent,
            ],
            'meta' => [
                'only_budgeted' => $onlyBudgeted,
                'default_threshold' => $defaultThreshold,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'transaction_category_id' => [
                'required',
                'integer',
                Rule::exists('transaction_categories', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'alert_threshold' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'transaction_category_id' => $validated['transaction_category_id'],
                'month' => $validated['month'],
            ],
            [
                'amount' => $validated['amount'],
                'alert_threshold' => $validated['alert_threshold'] ?? 80,
            ]
        );

        return response()->json([
            'id' => $budget->id,
            'month' => $budget->month,
            'transaction_category_id' => $budget->transaction_category_id,
            'amount' => (float) $budget->amount,
            'alert_threshold' => (int) $budget->alert_threshold,
        ]);
    }

    public function destroy(Request $request, Budget $budget)
    {
        abort_unless($budget->user_id === $request->user()->id, 403);
        $budget->delete();
        return response()->noContent();
    }

    public function copyPreviousMonth(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $targetMonth = $this->resolveMonth($validated['month']);
        $previousMonth = Carbon::createFromFormat('Y-m', $targetMonth)->subMonthNoOverflow()->format('Y-m');

        $previousBudgets = $request->user()
            ->budgets()
            ->where('month', $previousMonth)
            ->get();

        foreach ($previousBudgets as $budget) {
            Budget::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'transaction_category_id' => $budget->transaction_category_id,
                    'month' => $targetMonth,
                ],
                [
                    'amount' => $budget->amount,
                    'alert_threshold' => $budget->alert_threshold,
                ]
            );
        }

        return response()->json([
            'copied_count' => $previousBudgets->count(),
            'from_month' => $previousMonth,
            'to_month' => $targetMonth,
        ]);
    }

    private function resolveMonth(?string $input): string
    {
        if (!$input) return now()->format('Y-m');
        try {
            return Carbon::createFromFormat('Y-m', $input)->format('Y-m');
        } catch (\Throwable $e) {
            return now()->format('Y-m');
        }
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        return [$start, $end];
    }

    private function resolveThreshold($input): int
    {
        $threshold = (int) $input;
        if ($threshold < 1 || $threshold > 100) return 80;
        return $threshold;
    }
}
