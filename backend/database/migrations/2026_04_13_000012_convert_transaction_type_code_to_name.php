<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transaction_types
            MODIFY COLUMN name VARCHAR(100) NOT NULL
        ");

        DB::statement("
            UPDATE transaction_types
            SET name = CASE
                WHEN name = '1' THEN 'expense'
                WHEN name = '3' THEN 'income'
                ELSE 'other'
            END
        ");

        DB::statement("
            UPDATE transactions t
            INNER JOIN transaction_types src ON src.id = t.transaction_type_id
            INNER JOIN transaction_types keep_row
                ON keep_row.user_id = src.user_id
                AND keep_row.name = src.name
                AND keep_row.id = (
                    SELECT MIN(tt.id)
                    FROM transaction_types tt
                    WHERE tt.user_id = src.user_id
                      AND tt.name = src.name
                )
            SET t.transaction_type_id = keep_row.id
            WHERE t.transaction_type_id <> keep_row.id
        ");

        DB::statement("
            DELETE dup
            FROM transaction_types dup
            INNER JOIN transaction_types keep_row
                ON keep_row.user_id = dup.user_id
                AND keep_row.name = dup.name
                AND keep_row.id < dup.id
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE transaction_types
            SET name = CASE
                WHEN LOWER(TRIM(name)) = 'expense' THEN '1'
                WHEN LOWER(TRIM(name)) = 'income' THEN '3'
                ELSE '0'
            END
        ");

        DB::statement("
            ALTER TABLE transaction_types
            MODIFY COLUMN name TINYINT UNSIGNED NOT NULL DEFAULT 0
        ");
    }
};
