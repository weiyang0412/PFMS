<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2>Hi {{ $payload['name'] ?? 'there' }}, here is your financial report</h2>
    <p>Period: {{ $payload['period_label'] ?? 'Current Month' }}</p>
    <ul>
        <li>Total Income: RM {{ number_format((float) ($payload['income'] ?? 0), 2) }}</li>
        <li>Total Expense: RM {{ number_format((float) ($payload['expense'] ?? 0), 2) }}</li>
        <li>Net Cashflow: RM {{ number_format((float) ($payload['net'] ?? 0), 2) }}</li>
        <li>Savings Rate: {{ number_format((float) ($payload['savings_rate'] ?? 0), 1) }}%</li>
    </ul>
    <p>Open SmartBudget to review trend and category details.</p>
</body>
</html>

