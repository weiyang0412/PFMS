<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'month'], 'budgets_user_month_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'transaction_date', 'transaction_category_id'], 'transactions_user_date_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_month_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_date_category_idx');
        });
    }
};

