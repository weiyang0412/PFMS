<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'budget_alerts_enabled',
        'reports_enabled',
        'report_frequency',
        'report_weekday',
        'report_month_day',
        'last_report_sent_at',
        'last_reminder_sent_at',
        'last_budget_warning_sent_at',
        'last_budget_urgent_sent_at',
    ];

    protected $casts = [
        'budget_alerts_enabled' => 'boolean',
        'reports_enabled' => 'boolean',
        'report_weekday' => 'integer',
        'report_month_day' => 'integer',
        'last_report_sent_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'last_budget_warning_sent_at' => 'datetime',
        'last_budget_urgent_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
