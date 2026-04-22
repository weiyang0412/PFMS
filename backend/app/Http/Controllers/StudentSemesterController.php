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

        $studentSemester->update([
            'name' => trim($validated['name']),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json($this->serializeSemester($studentSemester->fresh()));
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

    private function serializeSemester(StudentSemester $semester): array
    {
        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'start_date' => optional($semester->start_date)->format('Y-m-d'),
            'end_date' => optional($semester->end_date)->format('Y-m-d'),
        ];
    }
}
