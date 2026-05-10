<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Alert</title>
</head>
@php
    $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
    $alerts = collect($payload['alerts'] ?? []);
    $isUrgent = strtolower((string) ($payload['alert_level'] ?? 'warning')) === 'urgent';
    $headerColor = $isUrgent ? '#7f1d1d' : '#92400e';
    $headerAccent = $isUrgent ? '#fecaca' : '#fde68a';
    $headerTint = $isUrgent ? '#fee2e2' : '#fef3c7';
@endphp
<body style="margin:0; padding:0; background:#f1f5f9; font-family:Arial, sans-serif; color:#0f172a;">
    <div style="max-width:680px; margin:0 auto; padding:32px 16px;">
        <div style="background:{{ $headerColor }}; color:#fff; border-radius:24px 24px 0 0; padding:28px 28px 22px;">
            <p style="margin:0 0 8px; font-size:12px; letter-spacing:0.22em; text-transform:uppercase; color:{{ $headerAccent }};">Budget Alert</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2;">
                Hi {{ $payload['name'] ?? 'there' }}, {{ $isUrgent ? 'your budget is over the limit.' : 'one or more budgets need attention.' }}
            </h1>
            <p style="margin:12px 0 0; font-size:15px; line-height:1.6; color:{{ $headerTint }};">
                {{ $payload['advice'] ?? 'Your budget is getting close to the limit, so this is a good time to review spending.' }}
            </p>
        </div>

        <div style="background:#ffffff; padding:28px; border-radius:0 0 24px 24px; box-shadow:0 18px 40px rgba(15, 23, 42, 0.08);">
            <p style="margin:0 0 18px; font-size:14px; color:#64748b;">Period: {{ $payload['period_label'] ?? 'Current Month' }}</p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate; border-spacing:12px; margin:0 -12px 8px;">
                <tr>
                    <td width="33.33%" style="background:#fff1f2; border:1px solid #fecaca; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#9f1239;">Alerts</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#7f1d1d;">{{ (int) ($payload['alert_count'] ?? 0) }}</p>
                    </td>
                    <td width="33.33%" style="background:#fff1f2; border:1px solid #fecaca; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#9f1239;">Top Usage</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#7f1d1d;">{{ number_format((float) ($payload['highest_usage_pct'] ?? 0), 1) }}%</p>
                    </td>
                    <td width="33.33%" style="background:#fff1f2; border:1px solid #fecaca; border-radius:18px; padding:18px;">
                        <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#9f1239;">Overspent</p>
                        <p style="margin:10px 0 0; font-size:24px; font-weight:700; color:#7f1d1d;">RM {{ number_format((float) ($payload['total_overspent'] ?? 0), 2) }}</p>
                    </td>
                </tr>
            </table>

            <div style="margin-top:18px; border:1px solid #fecaca; border-radius:20px; padding:20px; background:#fff7f8;">
                <p style="margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#9f1239;">Main Concern</p>
                <p style="margin:10px 0 0; font-size:16px; line-height:1.6; color:#0f172a;">
                    @if(!empty($payload['highest_category']))
                        <strong>{{ $payload['highest_category'] }}</strong> is currently your highest-usage category.
                    @else
                        One or more categories are now close to the configured alert threshold.
                    @endif
                </p>

                <p style="margin:18px 0 0; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#9f1239;">Suggested Next Step</p>
                <p style="margin:10px 0 0; font-size:16px; line-height:1.6; color:#0f172a;">
                    {{ $payload['advice'] ?? 'Review the related budgets and adjust upcoming spending if needed.' }}
                </p>
            </div>

            @if($alerts->isNotEmpty())
                <div style="margin-top:22px;">
                    <p style="margin:0 0 12px; font-size:12px; text-transform:uppercase; letter-spacing:0.14em; color:#64748b;">Active Alerts</p>
                    @foreach($alerts as $alert)
                        <div style="border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:16px; padding:16px 18px; margin-bottom:12px; background:#fff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="vertical-align:top;">
                                        <p style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">{{ $alert['category'] ?? 'Category' }}</p>
                                        <p style="margin:6px 0 0; font-size:13px; color:#64748b;">
                                            Remaining RM {{ number_format((float) ($alert['remaining'] ?? 0), 2) }}
                                            @if(!empty($alert['alert_threshold']))
                                                · Alert at {{ (int) $alert['alert_threshold'] }}%
                                            @endif
                                        </p>
                                    </td>
                                    <td style="vertical-align:top; text-align:right; white-space:nowrap;">
                                        <p style="margin:0; font-size:18px; font-weight:700; color:#ef4444;">{{ number_format((float) ($alert['usage_pct'] ?? 0), 1) }}%</p>
                                        <p style="margin:6px 0 0; font-size:13px; color:#64748b;">Spent RM {{ number_format((float) ($alert['spent'] ?? 0), 2) }} / RM {{ number_format((float) ($alert['amount'] ?? 0), 2) }}</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-top:24px; text-align:center;">
                <a href="{{ $frontendUrl }}/budgets" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:{{ $headerColor }}; color:#ffffff; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px;">Review Budgets</a>
                <a href="{{ $frontendUrl }}/transactions" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:#ffffff; color:{{ $headerColor }}; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px; border:1px solid {{ $headerAccent }};">Open Transactions</a>
                <a href="{{ $frontendUrl }}/dashboard" style="display:inline-block; margin:0 6px 10px; padding:12px 18px; background:{{ $headerTint }}; color:{{ $headerColor }}; text-decoration:none; border-radius:999px; font-weight:700; font-size:14px;">View Dashboard</a>
            </div>

            <p style="margin:20px 0 0; font-size:13px; line-height:1.6; color:#64748b; text-align:center;">
                SmartBudget sent this alert because one or more budget categories reached the configured threshold.
            </p>
        </div>
    </div>
</body>
</html>
