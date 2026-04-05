<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE transactions MODIFY type VARCHAR(100) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE transactions MODIFY type ENUM('income', 'expense') NOT NULL");
    }
};
