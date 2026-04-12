<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionCategory extends Model
{
    use HasFactory;

    public const APPLIES_TO_BOTH = 0;
    public const APPLIES_TO_EXPENSE = 1;
    public const APPLIES_TO_INCOME = 3;

    protected $fillable = [
        'user_id',
        'name',
        'applies_to',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAppliesToAttribute($value): string
    {
        return self::appliesToName($value);
    }

    public function setAppliesToAttribute($value): void
    {
        $this->attributes['applies_to'] = self::appliesToCode($value);
    }

    public static function appliesToCode($value): int
    {
        if (is_numeric($value)) {
            $numeric = (int) $value;
            if (in_array($numeric, [self::APPLIES_TO_BOTH, self::APPLIES_TO_EXPENSE, self::APPLIES_TO_INCOME], true)) {
                return $numeric;
            }
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'expense') {
            return self::APPLIES_TO_EXPENSE;
        }
        if ($normalized === 'income') {
            return self::APPLIES_TO_INCOME;
        }
        return self::APPLIES_TO_BOTH;
    }

    public static function appliesToName($value): string
    {
        $code = self::appliesToCode($value);
        if ($code === self::APPLIES_TO_EXPENSE) {
            return 'expense';
        }
        if ($code === self::APPLIES_TO_INCOME) {
            return 'income';
        }
        return 'both';
    }
}
