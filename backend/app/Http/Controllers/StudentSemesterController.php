<?php

namespace App\Http\Controllers;

use App\Models\StudentSemester;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentSemesterController extends Controller
{
    public function index(Request $request)
    {
        $semesters = $request->user()
            ->studentSemesters()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentSemester $semester) => $this->serializeSemester($semester))
            ->values();

        return response()->json([
            'items' => $semesters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        $this->validateDateRange($validated['start_date'], $validated['end_date']);
        $this->validateOverlap($request->user()->id, $validated['start_date'], $validated['end_date']);

        $semester = $request->user()->studentSemesters()->create([
            'name' => trim($validated['name']),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json($this->serializeSemester($semester), 201);
    }

    public function update(Request $request, StudentSemester $studentSemester)
    {
        abort_unless($studentSemester->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        $this->validateDateRange($validated['start_date'], $validated['end_date']);
        $this->validateOverlap(
            $request->user()->id,
            $validated['start_date'],
            $validated['end_date'],
            $studentSemester->id
        );

        $studentSemester->update([
            'name' => trim($validated['name']),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json($this->serializeSemester($studentSemester->fresh()));
    }

    public function copyPreviousSemester(Request $request)
    {
        $previousSemester = $request->user()
            ->studentSemesters()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (!$previousSemester) {
            throw ValidationException::withMessages([
                'semester' => ['Create your first semester before duplicating one.'],
            ]);
        }

        $start = Carbon::parse($previousSemester->start_date)->startOfDay();
        $end = Carbon::parse($previousSemester->end_date)->startOfDay();
        $durationDays = $start->diffInDays($end) + 1;
        $newStart = $end->copy()->addDay();
        $newEnd = $newStart->copy()->addDays($durationDays - 1);

        $semester = $request->user()->studentSemesters()->create([
            'name' => trim($previousSemester->name) . ' Copy',
            'start_date' => $newStart->toDateString(),
            'end_date' => $newEnd->toDateString(),
        ]);

        return response()->json($this->serializeSemester($semester), 201);
    }

    public function destroy(Request $request, StudentSemester $studentSemester)
    {
        abort_unless($studentSemester->user_id === $request->user()->id, 403);
        $studentSemester->delete();
        return response()->noContent();
    }

    private function validateDateRange(string $startDate, string $endDate): void
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['Semester end date must be on or after the start date.'],
            ]);
        }
    }

    private function validateOverlap(int $userId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        $query = StudentSemester::query()
            ->where('user_id', $userId)
            ->where(function ($builder) use ($start, $end) {
                $builder
                    ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($nested) use ($start, $end) {
                        $nested->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            });

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['This semester overlaps with an existing semester.'],
                'end_date' => ['This semester overlaps with an existing semester.'],
            ]);
        }
    }

    private function serializeSemester(StudentSemester $semester): array
    {
        $today = Carbon::today();
        $start = $semester->start_date;
        $end = $semester->end_date;
        $status = 'inactive';

        if ($start && $end) {
            if ($today->betweenIncluded($start, $end)) {
                $status = 'current';
            } elseif ($today->lt($start)) {
                $status = 'upcoming';
            }
        }

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'start_date' => optional($start)->format('Y-m-d'),
            'end_date' => optional($end)->format('Y-m-d'),
            'status' => $status,
            'is_current' => $status === 'current',
            'duration_days' => $start && $end ? $start->diffInDays($end) + 1 : null,
        ];
    }
}
