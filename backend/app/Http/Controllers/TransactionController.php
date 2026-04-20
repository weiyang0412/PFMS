<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $month = $request->query('month');

        if ($month !== null && !preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            return response()->json([
                'message' => 'Invalid month format. Use YYYY-MM.',
            ], 422);
        }

        $query = $request->user()
            ->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($month) {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
            $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
            $query->whereBetween('transaction_date', [$monthStart, $monthEnd]);
        }

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

        $validated = $request->validate([
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

        if (!empty($validated['transaction_category_id'])) {
            $transactionTypeName = strtolower((string) $request->user()
                ->transactionTypes()
                ->whereKey($validated['transaction_type_id'])
                ->value('name'));

            if (in_array($transactionTypeName, ['income', 'expense'], true)) {
                $appliesToRaw = $request->user()
                    ->transactionCategories()
                    ->whereKey($validated['transaction_category_id'])
                    ->value('applies_to');
                $appliesTo = TransactionCategory::appliesToName($appliesToRaw);

                if ($appliesTo !== 'both' && $appliesTo !== $transactionTypeName) {
                    throw ValidationException::withMessages([
                        'transaction_category_id' => ['Selected category is not valid for the chosen type.'],
                    ]);
                }
            }
        }

        return $validated;
    }

    private function ensureDefaultTypes(Request $request): void
    {
        if ($request->user()->transactionTypes()->exists()) {
            return;
        }

        foreach (['income', 'expense'] as $typeName) {
            $request->user()->transactionTypes()->create(['name' => $typeName]);
        }
    }
}
