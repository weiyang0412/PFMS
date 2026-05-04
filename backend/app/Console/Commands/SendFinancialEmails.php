<?php

namespace App\Console\Commands;

use App\Mail\FinancialReportMail;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendFinancialEmails extends Command
{
    protected $signature = 'emails:send-financial-reports';
    protected $description = 'Send reminder and periodic financial report emails';

    public function handle()
    {
        $today = Carbon::today();
        $weekday = (int) $today->dayOfWeekIso;
        $dayOfMonth = (int) $today->day;

        $preferences = NotificationPreference::with('user')
            ->where('reports_enabled', true)
            ->get();

        $sentCount = 0;
        foreach ($preferences as $preference) {
            if (!$preference->user || !$preference->user->email) {
                continue;
            }

            $shouldSend = false;
            if ($preference->report_frequency === 'weekly' && (int) $preference->report_weekday === $weekday) {
                $shouldSend = true;
            }
            if ($preference->report_frequency === 'monthly' && (int) $preference->report_month_day === $dayOfMonth) {
                $shouldSend = true;
            }
            if (!$shouldSend) {
                continue;
            }

            $payload = $this->buildCurrentMonthPayload($preference->user);
            Mail::to($preference->user->email)->send(new FinancialReportMail($payload));
            $preference->last_report_sent_at = now();
            $preference->save();
            $sentCount++;
        }

        $this->info("Financial report emails sent: {$sentCount}");
        return Command::SUCCESS;
    }

    private function buildCurrentMonthPayload($user): array
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();
        $transactions = $user->transactions()
            ->with(['transactionType:id,name'])
            ->whereBetween('transaction_date', [$start, $end])
            ->get(['amount', 'transaction_type_id']);

        $income = (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'income')
            ->sum('amount');
        $expense = (float) $transactions
            ->filter(fn ($transaction) => strtolower((string) optional($transaction->transactionType)->name) === 'expense')
            ->sum('amount');
        $net = $income - $expense;
        $savingsRate = $income > 0 ? ($net / $income) * 100 : 0;

        return [
            'name' => $user->name,
            'period_label' => Carbon::now()->format('F Y'),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($net, 2),
            'savings_rate' => round($savingsRate, 1),
        ];
    }
}
