<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('applies_to')
                ->default(0)
                ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
