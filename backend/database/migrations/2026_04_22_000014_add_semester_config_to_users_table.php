<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_start_month')->default(1)->after('preferred_period');
            $table->unsignedTinyInteger('semester_length_months')->default(6)->after('semester_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'semester_start_month',
                'semester_length_months',
            ]);
        });
    }
};
