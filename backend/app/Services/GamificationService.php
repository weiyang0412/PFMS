<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\StudentSemester;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GamificationService
{
    public function buildSummary(User $user, string $periodType, Carbon $anchorMonth, ?StudentSemester $semester = null): array
    {
        [$periodStart, $periodEnd, $periodLabel] = $periodType === 'semester'
            ? $this->semesterRange($semester, $anchorMonth)
            : $this->monthRange($anchorMonth);

        $transactions = $user->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $currentTransactions = $transactions->filter(function ($transaction) use ($periodStart, $periodEnd) {
            return $transaction->transaction_date !== null
                && $transaction->transaction_date->between($periodStart, $periodEnd);
        })->values();

        $budgetMonths = $periodType === 'semester'
            ? $this->monthsInRange($periodStart, $periodEnd)
            : [$anchorMonth->format('Y-m')];

        $currentBudgets = $user->budgets()
            ->with(['transactionCategory:id,name'])
            ->whereIn('month', $budgetMonths)
            ->get()
            ->values();

        $income = $this->sumByType($currentTransactions, 'income');
        $expense = $this->sumByType($currentTransactions, 'expense');
        $cashflow = $income - $expense;
        $savingsRate = $income > 0 ? round(($cashflow / $income) * 100, 1) : 0;

        $activeDays = $currentTransactions
            ->pluck('transaction_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->count();

        $streak = $this->calculateCurrentStreak($currentTransactions);
        $pointsBreakdown = $this->buildPointsBreakdown($currentTransactions, $currentBudgets, $savingsRate, $streak['days'], $cashflow, $expense, $currentBudgets->sum('amount'));
        $challenges = $this->buildChallenges($currentTransactions, $currentBudgets, $savingsRate, $streak['days'], $cashflow, $expense, $income);
        $challengeRewardPoints = collect($challenges)->where('completed', true)->sum('reward_points');
        $points = collect($pointsBreakdown)->sum('points') + $challengeRewardPoints;
        $pointsBreakdown[] = [
            'code' => 'challenge_rewards',
            'label' => 'Challenge rewards',
            'points' => $challengeRewardPoints,
            'detail' => sprintf('%d completed challenge%s', collect($challenges)->where('completed', true)->count(), collect($challenges)->where('completed', true)->count() === 1 ? '' : 's'),
        ];
        $level = max(1, intdiv($points, 250) + 1);
        $levelBase = ($level - 1) * 250;
        $nextLevelPoints = $level * 250;
        $levelProgress = $nextLevelPoints > $levelBase
            ? round((($points - $levelBase) / ($nextLevelPoints - $levelBase)) * 100, 1)
            : 0;

        $badgeDefinitions = $this->badgeDefinitions();
        $badges = array_map(function (array $definition) use ($currentTransactions, $currentBudgets, $savingsRate, $streak, $cashflow, $expense) {
            return $this->resolveBadge($definition, $currentTransactions, $currentBudgets, $savingsRate, $streak, $cashflow, $expense);
        }, $badgeDefinitions);

        $rewards = $this->buildRewards($points, $level, $levelProgress, $badges, $challenges);

        return [
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'updated_at' => $anchorMonth->copy()->endOfMonth()->format('Y-m-d'),
                'month' => $anchorMonth->format('Y-m'),
                'semester_id' => $semester ? $semester->id : null,
                'comparison_label' => $periodType === 'semester' ? 'vs selected semester' : 'vs selected month',
            ],
            'profile' => [
                'points' => $points,
                'level' => $level,
                'level_progress' => $levelProgress,
                'next_level_points' => $nextLevelPoints,
                'points_to_next_level' => max(0, $nextLevelPoints - $points),
                'active_days' => $activeDays,
                'streak_days' => $streak['days'],
                'transactions_count' => $currentTransactions->count(),
                'budget_count' => $currentBudgets->count(),
                'cashflow' => round($cashflow, 2),
                'savings_rate' => $savingsRate,
            ],
            'points_breakdown' => $pointsBreakdown,
            'badges' => $badges,
            'challenges' => $challenges,
            'rewards' => $rewards,
        ];
    }

    private function badgeDefinitions(): array
    {
        return [
            [
                'code' => 'first_step',
                'title' => 'First Step',
                'description' => 'Log your first transaction to start the streak.',
                'target' => 1,
                'kind' => 'transactions',
            ],
            [
                'code' => 'momentum_maker',
                'title' => 'Momentum Maker',
                'description' => 'Keep the momentum going with 5 transactions.',
                'target' => 5,
                'kind' => 'transactions',
            ],
            [
                'code' => 'budget_builder',
                'title' => 'Budget Builder',
                'description' => 'Create at least one budget for the selected period.',
                'target' => 1,
                'kind' => 'budgets',
            ],
            [
                'code' => 'savings_spark',
                'title' => 'Savings Spark',
                'description' => 'Reach a savings rate of 5% or more.',
                'target' => 5,
                'kind' => 'savings_rate',
            ],
            [
                'code' => 'savings_hero',
                'title' => 'Savings Hero',
                'description' => 'Reach a savings rate of 20% or more.',
                'target' => 20,
                'kind' => 'savings_rate',
            ],
            [
                'code' => 'streak_starter',
                'title' => 'Streak Starter',
                'description' => 'Stay active across 3 consecutive days.',
                'target' => 3,
                'kind' => 'streak',
            ],
            [
                'code' => 'streak_champion',
                'title' => 'Streak Champion',
                'description' => 'Build a 7-day activity streak.',
                'target' => 7,
                'kind' => 'streak',
            ],
            [
                'code' => 'discipline_guardian',
                'title' => 'Discipline Guardian',
                'description' => 'Keep expenses within the current budget.',
                'target' => 1,
                'kind' => 'budget_guardian',
            ],
        ];
    }

    private function resolveBadge(
        array $definition,
        Collection $transactions,
        Collection $budgets,
        float $savingsRate,
        array $streak,
        float $cashflow,
        float $expense
    ): array {
        $earned = false;
        $progress = 0.0;
        $earnedAt = null;

        switch ($definition['kind']) {
            case 'transactions':
                $count = $transactions->count();
                $progress = $definition['target'] > 0 ? min(100, round(($count / $definition['target']) * 100, 1)) : 0;
                $earned = $count >= $definition['target'];
                if ($earned) {
                    $milestoneTransaction = $transactions->sortBy('transaction_date')->values()->get($definition['target'] - 1);
                    $earnedAt = optional(optional($milestoneTransaction)->transaction_date)->toDateString();
                }
                break;
            case 'budgets':
                $count = $budgets->count();
                $progress = $definition['target'] > 0 ? min(100, round(($count / $definition['target']) * 100, 1)) : 0;
                $earned = $count >= $definition['target'];
                if ($earned) {
                    $firstBudget = $budgets->sortBy('created_at')->first();
                    $earnedAt = optional(optional($firstBudget)->created_at)->toDateString();
                }
                break;
            case 'savings_rate':
                $progress = $definition['target'] > 0 ? min(100, round(($savingsRate / $definition['target']) * 100, 1)) : 0;
                $earned = $savingsRate >= $definition['target'];
                if ($earned) {
                    $earnedAt = now()->toDateString();
                }
                break;
            case 'streak':
                $progress = $definition['target'] > 0 ? min(100, round(($streak['days'] / $definition['target']) * 100, 1)) : 0;
                $earned = $streak['days'] >= $definition['target'];
                if ($earned) {
                    $earnedAt = $streak['last_date'];
                }
                break;
            case 'budget_guardian':
                $budgetTotal = (float) $budgets->sum('amount');
                $progress = $budgetTotal > 0
                    ? min(100, round((($budgetTotal - max(0, $expense)) / $budgetTotal) * 100, 1))
                    : 0;
                $earned = $budgetTotal > 0 && $cashflow >= 0 && $expense <= $budgetTotal;
                if ($earned) {
                    $earnedAt = now()->toDateString();
                }
                break;
        }

        return array_merge($definition, [
            'earned' => $earned,
            'earned_at' => $earnedAt,
            'progress' => round($progress, 1),
        ]);
    }

    private function buildChallenges(Collection $transactions, Collection $budgets, float $savingsRate, int $streakDays, float $cashflow, float $expense, float $income): array
    {
        $challengeItems = [
            [
                'code' => 'log_5_transactions',
                'title' => 'Log 5 transactions',
                'description' => 'Build a visible activity trail for the current period.',
                'target' => 5,
                'progress' => $transactions->count(),
                'reward_points' => 0,
            ],
            [
                'code' => 'create_3_budgets',
                'title' => 'Create 3 budgets',
                'description' => 'Cover more categories with budget guardrails.',
                'target' => 3,
                'progress' => $budgets->count(),
                'reward_points' => 0,
            ],
            [
                'code' => 'save_15_percent',
                'title' => 'Save 15%',
                'description' => 'Keep your savings rate in healthy territory.',
                'target' => 15,
                'progress' => $savingsRate,
                'reward_points' => 0,
            ],
            [
                'code' => 'grow_streak_7',
                'title' => 'Grow a 7-day streak',
                'description' => 'Stay consistent and return every day.',
                'target' => 7,
                'progress' => $streakDays,
                'reward_points' => 0,
            ],
            [
                'code' => 'protect_cashflow',
                'title' => 'Protect positive cashflow',
                'description' => 'Finish the period with income ahead of expenses.',
                'target' => 1,
                'progress' => $cashflow >= 0 ? 1 : 0,
                'reward_points' => 70,
            ],
        ];

        return array_map(function (array $challenge) use ($income, $expense) {
            $progressValue = (float) $challenge['progress'];
            $target = (float) $challenge['target'];
            $progress = $target > 0 ? min(100, round(($progressValue / $target) * 100, 1)) : 0;

            return array_merge($challenge, [
                'progress' => round($progress, 1),
                'completed' => $progress >= 100,
                'status' => $progress >= 100 ? 'completed' : ($progress > 0 ? 'active' : 'pending'),
                'reward_label' => $challenge['reward_points'] > 0 ? $challenge['reward_points'] . ' pts' : 'Badge only',
                'subtext' => $challenge['code'] === 'protect_cashflow'
                    ? sprintf('Income: %s | Expense: %s', number_format($income, 2), number_format($expense, 2))
                    : null,
            ]);
        }, $challengeItems);
    }

    private function buildRewards(float $points, int $level, float $levelProgress, array $badges, array $challenges): array
    {
        $badgeCount = collect($badges)->where('earned', true)->count();
        $challengeCount = collect($challenges)->where('completed', true)->count();

        return [
            [
                'code' => 'bronze_chest',
                'title' => 'Bronze Chest',
                'description' => 'Unlock the first reward track milestone.',
                'threshold' => 100,
                'unlocked' => $points >= 100,
            ],
            [
                'code' => 'silver_chest',
                'title' => 'Silver Chest',
                'description' => 'Reach a stronger habit loop and earn more rewards.',
                'threshold' => 250,
                'unlocked' => $points >= 250,
            ],
            [
                'code' => 'gold_chest',
                'title' => 'Gold Chest',
                'description' => 'Hit a serious momentum milestone.',
                'threshold' => 500,
                'unlocked' => $points >= 500,
            ],
            [
                'code' => 'master_chest',
                'title' => 'Master Chest',
                'description' => 'Reserve for consistent high performers.',
                'threshold' => 1000,
                'unlocked' => $points >= 1000,
            ],
            [
                'code' => 'level_snapshot',
                'title' => 'Current Level Snapshot',
                'description' => sprintf('Level %d progress is at %.1f%%.', $level, $levelProgress),
                'threshold' => null,
                'unlocked' => true,
            ],
            [
                'code' => 'milestone_meter',
                'title' => 'Milestone Meter',
                'description' => sprintf('%d badges and %d challenges completed.', $badgeCount, $challengeCount),
                'threshold' => null,
                'unlocked' => true,
            ],
        ];
    }

    private function buildPointsBreakdown(Collection $transactions, Collection $budgets, float $savingsRate, int $streakDays, float $cashflow, float $expense, float $budgetTotal): array
    {
        $activityPoints = $transactions->count() * 10;
        $budgetPoints = $budgets->count() * 20;
        $savingsPoints = $savingsRate >= 20 ? 100 : ($savingsRate >= 10 ? 50 : ($savingsRate >= 5 ? 25 : 0));
        $streakPoints = $streakDays * 5;
        $disciplinePoints = $budgetTotal > 0 && $expense <= $budgetTotal && $cashflow >= 0 ? 40 : 0;

        return [
            [
                'code' => 'activity',
                'label' => 'Activity bonus',
                'points' => $activityPoints,
                'detail' => sprintf('%d transaction%s x 10 pts', $transactions->count(), $transactions->count() === 1 ? '' : 's'),
            ],
            [
                'code' => 'budget',
                'label' => 'Budget builder',
                'points' => $budgetPoints,
                'detail' => sprintf('%d budget%s x 20 pts', $budgets->count(), $budgets->count() === 1 ? '' : 's'),
            ],
            [
                'code' => 'savings',
                'label' => 'Savings bonus',
                'points' => $savingsPoints,
                'detail' => sprintf('Savings rate: %.1f%%', $savingsRate),
            ],
            [
                'code' => 'streak',
                'label' => 'Consistency streak',
                'points' => $streakPoints,
                'detail' => sprintf('%d active day%s', $streakDays, $streakDays === 1 ? '' : 's'),
            ],
            [
                'code' => 'discipline',
                'label' => 'Discipline bonus',
                'points' => $disciplinePoints,
                'detail' => $budgetTotal > 0 ? 'Stayed within budget guardrails' : 'Create a budget to unlock this bonus',
            ],
        ];
    }

    private function sumByType(Collection $transactions, string $type): float
    {
        return (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === $type)
            ->sum('amount');
    }

    private function calculateCurrentStreak(Collection $transactions): array
    {
        $dates = $transactions
            ->pluck('transaction_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return [
                'days' => 0,
                'last_date' => null,
            ];
        }

        $dateSet = array_flip($dates->all());
        $cursor = Carbon::parse($dates->last());
        $streak = 1;

        while (true) {
            $previousDay = $cursor->copy()->subDay()->toDateString();
            if (!isset($dateSet[$previousDay])) {
                break;
            }

            $streak++;
            $cursor->subDay();
        }

        return [
            'days' => $streak,
            'last_date' => $dates->last(),
        ];
    }

    private function monthRange(Carbon $anchorMonth): array
    {
        $start = $anchorMonth->copy()->startOfMonth()->startOfDay();
        $end = $anchorMonth->copy()->endOfMonth()->endOfDay();

        return [$start, $end, $start->format('F Y')];
    }

    private function semesterRange(?StudentSemester $semester, Carbon $anchorMonth): array
    {
        if ($semester) {
            return [
                Carbon::parse($semester->start_date)->startOfDay(),
                Carbon::parse($semester->end_date)->endOfDay(),
                $semester->name,
            ];
        }

        return $this->monthRange($anchorMonth);
    }

    private function monthsInRange(Carbon $start, Carbon $end): array
    {
        $cursor = $start->copy()->startOfMonth();
        $months = [];

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return array_values(array_unique($months));
    }
}
