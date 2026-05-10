<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('budget_alerts_enabled')->default(true)->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('budget_alerts_enabled');
        });
    }
};
