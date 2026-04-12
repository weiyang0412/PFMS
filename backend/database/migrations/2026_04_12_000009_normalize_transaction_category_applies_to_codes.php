<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transaction_categories')
            ->select(['id', 'applies_to'])
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) $row->applies_to));
                    if ($normalized === '1' || $normalized === 'expense') {
                        $code = 1;
                    } elseif ($normalized === '3' || $normalized === 'income') {
                        $code = 3;
                    } else {
                        $code = 0;
                    }

                    DB::table('transaction_categories')
                        ->where('id', $row->id)
                        ->update(['applies_to' => $code]);
                }
            });
    }

    public function down(): void
    {
        DB::table('transaction_categories')
            ->select(['id', 'applies_to'])
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) $row->applies_to));
                    if ($normalized === '1' || $normalized === 'expense') {
                        $code = 1;
                    } elseif ($normalized === '3' || $normalized === 'income') {
                        $code = 3;
                    } else {
                        $code = 0;
                    }

                    DB::table('transaction_categories')
                        ->where('id', $row->id)
                        ->update(['applies_to' => $code]);
                }
            });
    }
};
