<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE transaction_categories
            SET applies_to = CASE
                WHEN LOWER(TRIM(applies_to)) = 'expense' OR TRIM(applies_to) = '1' THEN '1'
                WHEN LOWER(TRIM(applies_to)) = 'income' OR TRIM(applies_to) = '3' THEN '3'
                ELSE '0'
            END
        ");

        DB::statement("
            ALTER TABLE transaction_categories
            MODIFY COLUMN applies_to TINYINT UNSIGNED NOT NULL DEFAULT 0
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE transaction_categories
            MODIFY COLUMN applies_to VARCHAR(20) NOT NULL DEFAULT '0'
        ");
    }
};
