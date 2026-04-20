<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('transaction_type_id')
                ->nullable()
                ->after('amount')
                ->constrained('transaction_types')
                ->cascadeOnDelete();
            $table->foreignId('transaction_category_id')
                ->nullable()
                ->after('transaction_type_id')
                ->constrained('transaction_categories')
                ->nullOnDelete();
        });

        $transactions = DB::table('transactions')->get();

        foreach ($transactions as $transaction) {
            $typeName = strtolower(trim((string) $transaction->type));
            $typeId = DB::table('transaction_types')->where([
                'user_id' => $transaction->user_id,
                'name' => $typeName,
            ])->value('id');

            if (!$typeId) {
                $typeId = DB::table('transaction_types')->insertGetId([
                    'user_id' => $transaction->user_id,
                    'name' => $typeName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $categoryId = null;

            if (!empty($transaction->category)) {
                $categoryId = DB::table('transaction_categories')->where([
                    'user_id' => $transaction->user_id,
                    'name' => $transaction->category,
                ])->value('id');

                if (!$categoryId) {
                    $categoryId = DB::table('transaction_categories')->insertGetId([
                        'user_id' => $transaction->user_id,
                        'name' => $transaction->category,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'transaction_type_id' => $typeId,
                    'transaction_category_id' => $categoryId,
                ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['type', 'category']);
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 100)->nullable()->after('amount');
            $table->string('category')->nullable()->after('transaction_type_id');
        });

        $transactions = DB::table('transactions')->get();

        foreach ($transactions as $transaction) {
            $typeName = DB::table('transaction_types')->where('id', $transaction->transaction_type_id)->value('name');
            $categoryName = DB::table('transaction_categories')->where('id', $transaction->transaction_category_id)->value('name');

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'type' => $typeName,
                    'category' => $categoryName,
                ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_type_id');
            $table->dropConstrainedForeignId('transaction_category_id');
        });

        DB::statement("ALTER TABLE transactions MODIFY type VARCHAR(100) NOT NULL");
    }
};
