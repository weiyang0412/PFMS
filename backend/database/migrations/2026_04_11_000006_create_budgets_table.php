<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_category_id')->constrained('transaction_categories')->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('alert_threshold')->default(80);
            $table->timestamps();

            $table->unique(['user_id', 'transaction_category_id', 'month'], 'budgets_user_category_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};

