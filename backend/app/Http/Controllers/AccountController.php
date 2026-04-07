<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = $request->user()
            ->accounts()
            ->latest()
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $account->balance,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'accounts' => $accounts,
            'total_balance' => $accounts->sum(fn ($account) => (float) $account['balance']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateAccount($request);

        $account = $request->user()->accounts()->create($validated);

        return response()->json($account, 201);
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($request->user()->id === $account->user_id, 403);

        $validated = $this->validateAccount($request);

        $account->update($validated);

        return response()->json($account);
    }

    public function destroy(Request $request, Account $account)
    {
        abort_unless($request->user()->id === $account->user_id, 403);

        $account->delete();

        return response()->noContent();
    }

    private function validateAccount(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);
    }
}
