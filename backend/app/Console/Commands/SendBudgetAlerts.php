<?php

namespace App\Console\Commands;

use App\Mail\BudgetAlertMail;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendBudgetAlerts extends Command
{
    protected $signature = 'emails:send-budget-alerts';
    protected $description = 'Send budget alert emails when spending nears the budget limit';

    public function handle()
    {
        $today = Carbon::today();
        $currentMonth = $today->format('Y-m');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $preferences = NotificationPreference::with('user')
            ->where('budget_alerts_enabled', true)
            ->get();

        $sentCount = 0;

        foreach ($preferences as $preference) {
            if (!$preference->user || !$preference->user->email) {
                continue;
            }

            $alerts = $this->buildBudgetAlertsForUser($preference->user, $currentMonth);

            if ($alerts->isEmpty()) {
                continue;
            }

            $alertLevel = $this->resolveAlertLevel($alerts);
            if ($alertLevel === 'urgent') {
                if ($this->alreadySentThisBudgetMonth($preference->last_budget_urgent_sent_at, $currentMonth)) {
                    continue;
                }
            } elseif ($this->alreadySentThisBudgetMonth($preference->last_budget_warning_sent_at, $currentMonth)) {
                continue;
            }

            $payload = [
                'name' => $preference->user->name,
                'period_label' => Carbon::now()->format('F Y'),
                'alert_level' => $alertLevel,
                'alert_count' => $alerts->count(),
                'highest_usage_pct' => (float) $alerts->max('usage_pct'),
                'highest_category' => $alerts->sortByDesc('usage_pct')->first()['category'] ?? null,
                'total_overspent' => round((float) $alerts->sum(fn ($alert) => max(0, (float) ($alert['spent'] ?? 0) - (float) ($alert['amount'] ?? 0))), 2),
                'advice' => $this->buildAdvice($alerts),
                'alerts' => $alerts->values()->all(),
                'frontend_url' => $frontendUrl,
            ];

            Mail::to($preference->user->email)->send(new BudgetAlertMail($payload));
            $preference->last_reminder_sent_at = now();
            if ($alertLevel === 'urgent') {
                $preference->last_budget_urgent_sent_at = now();
            } else {
                $preference->last_budget_warning_sent_at = now();
            }
            $preference->save();
            $sentCount++;
        }

        $this->info("Budget alert emails sent: {$sentCount}");

        return Command::SUCCESS;
    }

    private function resolveAlertLevel($alerts): string
    {
        $highestUsage = (float) $alerts->max('usage_pct');

        return $highestUsage >= 100 ? 'urgent' : 'warning';
    }

    private function alreadySentThisBudgetMonth(?Carbon $sentAt, string $currentMonth): bool
    {
        return $sentAt !== null && $sentAt->format('Y-m') === $currentMonth;
    }

    private function buildAdvice($alerts): string
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
}
