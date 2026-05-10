<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report</title>
</head>
@php
    $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
@endphp
<body style="margin:0; padding:0; background:#f1f5f9; font-family:Arial, sans-serif; color:#0f172a;">
    <div style="max-width:680px; margin:0 auto; padding:32px 16px;">
        <div style="background:#0f172a; color:#fff; border-radius:24px 24px 0 0; padding:28px 28px 22px;">
            <p style="margin:0 0 8px; font-size:12px; letter-spacing:0.22em; text-transform:uppercase; color:#94a3b8;">Financial Report</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2;">Hi {{ $payload['name'] ?? 'there' }}, here is your monthly snapshot.</h1>
            <p style="margin:12px 0 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                {{ $payload['insight'] ?? 'Your report is ready with a quick summary of this month.' }}
            </p>
        </div>

        <div style="background:#ffffff; padding:28px; border-radius:0 0 24px 24px; box-shadow:0 18px 40px rgba(15, 23, 42, 0.08);">
            <p style="margin:0 0 18px; font-size:14px; color:#64748b;">Period: {{ $payload['period_label'] ?? 'Current Month' }}</p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate; border-spacing:12px; margin:0 -12px 8px;">
                <tr>
                    <td width="50%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Income</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#0f172a;">RM {{ number_format((float) ($payload['income'] ?? 0), 2) }}</p>
                        @if (!is_null($payload['income_change_pct'] ?? null))
                            <p style="margin:8px 0 0; font-size:13px; color:#64748b;">vs last month: {{ number_format((float) $payload['income_change_pct'], 1) }}%</p>
                        @endif
                    </td>
                    <td width="50%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Expense</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#0f172a;">RM {{ number_format((float) ($payload['expense'] ?? 0), 2) }}</p>
                        @if (!is_null($payload['expense_change_pct'] ?? null))
                            <p style="margin:8px 0 0; font-size:13px; color:#64748b;">vs last month: {{ number_format((float) $payload['expense_change_pct'], 1) }}%</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Net Cashflow</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#0f172a;">RM {{ number_format((float) ($payload['net'] ?? 0), 2) }}</p>
                    </td>
                    <td width="50%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Savings Rate</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#0f172a;">{{ number_format((float) ($payload['savings_rate'] ?? 0), 1) }}%</p>
                    </td>
                </tr>
            </table>

            <div style="margin-top:18px; border:1px solid #e2e8f0; border-radius:20px; padding:20px; background:#f8fafc;">
                <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Insight</p>
                <p style="margin:10px 0 0; font-size:16px; line-height:1.6; color:#0f172a;">
                    {{ $payload['insight'] ?? 'Your spending pattern looks steady this month.' }}
                </p>

                <p style="margin:18px 0 0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Suggested Next Step</p>
                <p style="margin:10px 0 0; font-size:16px; line-height:1.6; color:#0f172a;">
                    {{ $payload['advice'] ?? 'Open SmartBudget to review your transactions and budgets.' }}
                </p>

                @if (!empty($payload['top_expense_category']))
                    <p style="margin:18px 0 0; font-size:14px; color:#475569;">
                        Top expense category: <strong>{{ $payload['top_expense_category'] }}</strong>
                        @if (!is_null($payload['top_expense_category_amount'] ?? null))
                            with RM {{ number_format((float) $payload['top_expense_category_amount'], 2) }} spent.
                        @endif
                    </p>
                @endif
            </div>

            <div style="margin-top:24px; text-align:center;">
                <a href="{{ $frontendUrl }}/dashboard" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:#0f172a; color:#ffffff; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px;">View Dashboard</a>
                <a href="{{ $frontendUrl }}/budgets" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:#ffffff; color:#0f172a; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px; border:1px solid #cbd5e1;">Review Budgets</a>
                <a href="{{ $frontendUrl }}/transactions" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:#e2e8f0; color:#0f172a; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px;">Open Transactions</a>
            </div>

            <p style="margin:20px 0 0; font-size:13px; line-height:1.6; color:#64748b; text-align:center;">
                You received this report because SmartBudget email reports are enabled for your account.
            </p>
        </div>
    </div>
</body>
</html>
