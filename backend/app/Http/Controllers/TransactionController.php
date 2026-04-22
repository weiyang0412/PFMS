<?php

namespace App\Http\Controllers;

use App\Models\StudentSemester;
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
        $month = $this->resolveMonth($request->query('month'));
        $period = $this->resolvePeriod($request->query('period'), $request->user()->profile_type);
        $selectedSemester = $period === 'semester'
            ? $this->resolveStudentSemester($request, $month)
            : null;
        [$periodStart, $periodEnd] = $period === 'semester'
            ? $this->semesterRange($selectedSemester, $month)
            : $this->monthRange($month);

        $query = $request->user()
            ->transactions()
            ->with(['transactionType:id,name', 'transactionCategory:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $query->whereBetween('transaction_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

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
        $transactions->appends([
            'month' => $month,
            'period' => $period,
            'semester_id' => $selectedSemester ? $selectedSemester->id : null,
        ]);
        $transactions->setCollection(
            $transactions->getCollection()
                ->map(fn ($item) => array_merge($item, [
                    'period_type' => $period,
                    'semester_id' => $selectedSemester ? $selectedSemester->id : null,
                ]))
        );

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

    private function resolveMonth(?string $input): string
    {
        if ($input !== null && !preg_match('/^\d{4}-\d{2}$/', (string) $input)) {
            throw ValidationException::withMessages([
                'month' => ['Invalid month format. Use YYYY-MM.'],
            ]);
        }

        try {
            if ($input) {
                return Carbon::createFromFormat('Y-m', $input)->format('Y-m');
            }
        } catch (\Throwable $e) {
        }

        return now()->format('Y-m');
    }

    private function resolvePeriod(?string $input, ?string $profileType): string
    {
        if ($profileType !== 'student') {
            return 'monthly';
        }

        return in_array($input, ['monthly', 'semester'], true) ? $input : 'monthly';
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        return [$start, $end];
    }

    private function semesterRange(?StudentSemester $semester, string $month): array
    {
        if ($semester) {
            return [
                Carbon::parse($semester->start_date)->startOfDay(),
                Carbon::parse($semester->end_date)->endOfDay(),
            ];
        }

        return $this->monthRange($month);
    }

    private function resolveStudentSemester(Request $request, string $month): ?StudentSemester
    {
        $semesterId = (int) $request->query('semester_id');
        if ($semesterId > 0) {
            return $request->user()->studentSemesters()->whereKey($semesterId)->first();
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $active = $request->user()->studentSemesters()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->orderBy('start_date')
            ->first();

        if ($active) {
            return $active;
        }

        return $request->user()->studentSemesters()
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
    }
}
