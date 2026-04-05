<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionOptionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/users', function (Request $request) {
        return User::all();
    });

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::get('/transaction-options', [TransactionOptionController::class, 'index']);
    Route::post('/transaction-options/types', [TransactionOptionController::class, 'storeType']);
    Route::delete('/transaction-options/types/{transactionType}', [TransactionOptionController::class, 'destroyType']);
    Route::post('/transaction-options/categories', [TransactionOptionController::class, 'storeCategory']);
    Route::delete('/transaction-options/categories/{transactionCategory}', [TransactionOptionController::class, 'destroyCategory']);
});
