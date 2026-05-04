<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('reminders_enabled')->default(true);
            $table->tinyInteger('reminder_days_before')->default(3);
            $table->boolean('reports_enabled')->default(true);
            $table->string('report_frequency', 20)->default('weekly');
            $table->tinyInteger('report_weekday')->default(1);
            $table->tinyInteger('report_month_day')->default(1);
            $table->timestamp('last_report_sent_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['reports_enabled', 'report_frequency']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_preferences');
    }
};

