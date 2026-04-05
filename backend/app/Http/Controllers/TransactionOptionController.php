<?php

namespace App\Http\Controllers;

use App\Models\TransactionCategory;
use App\Models\TransactionType;
use Illuminate\Http\Request;

class TransactionOptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $this->ensureDefaultTypes($user);

        return response()->json([
            'types' => $user->transactionTypes()->orderBy('name')->get(),
            'categories' => $user->transactionCategories()->orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $type = $request->user()->transactionTypes()->firstOrCreate([
            'name' => trim($validated['name']),
        ]);

        return response()->json($type, 201);
    }

    public function destroyType(Request $request, TransactionType $transactionType)
    {
        abort_unless($request->user()->id === $transactionType->user_id, 403);

        if ($request->user()->transactionTypes()->count() <= 1) {
            return response()->json([
                'message' => 'At least one type must remain available.',
            ], 422);
        }

        $isInUse = $request->user()
            ->transactions()
            ->where('transaction_type_id', $transactionType->id)
            ->exists();

        if ($isInUse) {
            return response()->json([
                'message' => 'This type is still being used by existing transactions.',
            ], 422);
        }

        $transactionType->delete();

        return response()->noContent();
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category = $request->user()->transactionCategories()->firstOrCreate([
            'name' => trim($validated['name']),
        ]);

        return response()->json($category, 201);
    }

    public function destroyCategory(Request $request, TransactionCategory $transactionCategory)
    {
        abort_unless($request->user()->id === $transactionCategory->user_id, 403);

        $isInUse = $request->user()
            ->transactions()
            ->where('transaction_category_id', $transactionCategory->id)
            ->exists();

        if ($isInUse) {
            return response()->json([
                'message' => 'This category is still being used by existing transactions.',
            ], 422);
        }

        $transactionCategory->delete();

        return response()->noContent();
    }

    private function ensureDefaultTypes($user): void
    {
        if ($user->transactionTypes()->exists()) {
            return;
        }

        foreach (['income', 'expense'] as $name) {
            $user->transactionTypes()->create(['name' => $name]);
        }
    }
}
