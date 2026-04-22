<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_type', 20)->nullable()->after('password');
            $table->string('preferred_period', 20)->default('monthly')->after('profile_type');
            $table->timestamp('onboarding_completed_at')->nullable()->after('preferred_period');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_type',
                'preferred_period',
                'onboarding_completed_at',
            ]);
        });
    }
};
