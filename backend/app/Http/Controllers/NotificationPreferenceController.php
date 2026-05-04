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
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_days_before' => ['required', 'integer', 'between:1,30'],
            'reports_enabled' => ['required', 'boolean'],
            'report_frequency' => ['required', Rule::in(['weekly', 'monthly'])],
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

        return response()->json(['message' => 'Test report email sent.']);
    }

    private function resolvePreference(int $userId): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $userId],
            [
                'reminders_enabled' => true,
                'reminder_days_before' => 3,
                'reports_enabled' => true,
                'report_frequency' => 'weekly',
                'report_weekday' => 1,
                'report_month_day' => 1,
            ]
        );
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
