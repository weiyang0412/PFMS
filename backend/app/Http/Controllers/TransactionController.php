<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $query = $request->user()
            ->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $transactions = $query->paginate($perPage, ['*'], 'page', $page);
        $transactions->through(function ($transaction) {
            return [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'transaction_type_id' => $transaction->transaction_type_id,
                'transaction_category_id' => $transaction->transaction_category_id,
                'type' => optional($transaction->transactionType)->name,
                'category' => optional($transaction->transactionCategory)->name,
                'transaction_date' => optional($transaction->transaction_date)->format('Y-m-d'),
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ];
        });

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTransaction($request);

        $transaction = $request->user()->transactions()->create($validated);

        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($request->user()->id === $transaction->user_id, 403);

        $validated = $this->validateTransaction($request);

        $transaction->update($validated);

        return response()->json($transaction);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        abort_unless($request->user()->id === $transaction->user_id, 403);

        $transaction->delete();

        return response()->noContent();
    }

    private function validateTransaction(Request $request): array
    {
        $this->ensureDefaultTypes($request);

        return $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_type_id' => [
                'required',
                'integer',
                Rule::exists('transaction_types', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'transaction_category_id' => [
                'nullable',
                'integer',
                Rule::exists('transaction_categories', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'transaction_date' => 'required|date',
        ]);
    }

    private function ensureDefaultTypes(Request $request): void
    {
        if ($request->user()->transactionTypes()->exists()) {
            return;
        }

        foreach (['income', 'expense'] as $name) {
            $request->user()->transactionTypes()->create(['name' => $name]);
        }
    }
}
