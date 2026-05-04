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
        'reminders_enabled',
        'reminder_days_before',
        'reports_enabled',
        'report_frequency',
        'report_weekday',
        'report_month_day',
    ];

    protected $casts = [
        'reminders_enabled' => 'boolean',
        'reports_enabled' => 'boolean',
        'reminder_days_before' => 'integer',
        'report_weekday' => 'integer',
        'report_month_day' => 'integer',
        'last_report_sent_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
