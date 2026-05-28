<?php

namespace App\Http\Controllers;

use App\Models\StudentSemester;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    private GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $periodType = $this->resolvePeriodType($request->query('period'), $user->profile_type);
        $anchorMonth = $this->resolveAnchorMonth($request->query('month'));
        $selectedSemester = $periodType === 'semester'
            ? $this->resolveStudentSemester($request, $anchorMonth)
            : null;

        return response()->json(
            $this->gamificationService->buildSummary($user, $periodType, $anchorMonth, $selectedSemester)
        );
    }

    private function resolvePeriodType(?string $input, ?string $profileType): string
    {
        if ($profileType !== 'student') {
            return 'monthly';
        }

        return in_array($input, ['monthly', 'semester'], true) ? $input : 'monthly';
    }

    private function resolveAnchorMonth(?string $input): Carbon
    {
        try {
            if ($input && preg_match('/^\d{4}-\d{2}$/', $input)) {
                return Carbon::createFromFormat('Y-m', $input)->startOfMonth();
            }
        } catch (\Throwable $e) {
        }

        return Carbon::today()->startOfMonth();
    }

    private function resolveStudentSemester(Request $request, Carbon $anchorMonth): ?StudentSemester
    {
        $semesterId = (int) $request->query('semester_id');
        if ($semesterId > 0) {
            return $request->user()->studentSemesters()->whereKey($semesterId)->first();
        }

        $monthStart = $anchorMonth->copy()->startOfMonth();
        $monthEnd = $anchorMonth->copy()->endOfMonth();

        $matchedSemester = $request->user()->studentSemesters()
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->orderBy('start_date')
            ->first();

        if ($matchedSemester) {
            return $matchedSemester;
        }

        return $request->user()->studentSemesters()
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
    }
}
