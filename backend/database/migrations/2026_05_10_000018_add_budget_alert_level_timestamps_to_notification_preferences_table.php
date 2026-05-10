<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->timestamp('last_budget_warning_sent_at')->nullable()->after('last_reminder_sent_at');
            $table->timestamp('last_budget_urgent_sent_at')->nullable()->after('last_budget_warning_sent_at');
        });
    }

    public function down()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['last_budget_warning_sent_at', 'last_budget_urgent_sent_at']);
        });
    }
};
