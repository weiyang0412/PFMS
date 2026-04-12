<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionType extends Model
{
    use HasFactory;

    public const TYPE_OTHER = 0;
    public const TYPE_EXPENSE = 1;
    public const TYPE_INCOME = 3;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute($value): string
    {
        return self::typeName($value);
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = self::typeCode($value);
    }

    public static function typeCode($value): int
    {
        if (is_numeric($value)) {
            $numeric = (int) $value;
            if (in_array($numeric, [self::TYPE_OTHER, self::TYPE_EXPENSE, self::TYPE_INCOME], true)) {
                return $numeric;
            }
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'expense') {
            return self::TYPE_EXPENSE;
        }
        if ($normalized === 'income') {
            return self::TYPE_INCOME;
        }
        return self::TYPE_OTHER;
    }

    public static function typeName($value): string
    {
        $code = self::typeCode($value);
        if ($code === self::TYPE_EXPENSE) {
            return 'expense';
        }
        if ($code === self::TYPE_INCOME) {
            return 'income';
        }
        return 'other';
    }
}
