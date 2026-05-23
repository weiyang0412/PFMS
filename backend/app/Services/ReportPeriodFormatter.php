<?php

namespace App\Services;

use Carbon\Carbon;

class ReportPeriodFormatter
{
    public function isSemester(array $report): bool
    {
        $period = $report['period'] ?? [];
        $reportType = strtolower(trim((string) ($report['report_type'] ?? 'general')));
        $periodType = strtolower(trim((string) ($report['period_type'] ?? 'monthly')));
        $preferredPeriod = strtolower(trim($this->preferredPeriod($report)));

        return $periodType === 'semester'
            || $reportType === 'student'
            || $preferredPeriod === 'semester'
            || !empty($period['semester'])
            || !empty($period['semester_label'])
            || !empty($period['semester_name']);
    }

    public function format(array $report): string
    {
        $period = $report['period'] ?? [];
        $reportType = strtolower(trim((string) ($report['report_type'] ?? 'general')));
        $periodType = strtolower(trim((string) ($report['period_type'] ?? 'monthly')));

        $label = trim((string) ($period['label'] ?? $report['period_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        if ($periodType === 'semester' || $reportType === 'student') {
            $semesterLabel = trim((string) (
                $period['semester_label']
                ?? $period['semester_name']
                ?? $report['semester_label']
                ?? $report['semester_name']
                ?? ''
            ));
            if ($semesterLabel !== '') {
                return $semesterLabel;
            }

            $semester = trim((string) ($period['semester'] ?? $report['semester'] ?? ''));
            $academicYear = trim((string) ($period['academic_year'] ?? $report['academic_year'] ?? ''));

            if ($semester !== '' && $academicYear !== '') {
                return 'Semester ' . $semester . ' ' . $academicYear;
            }

            if ($academicYear !== '') {
                return $academicYear;
            }

            return 'Selected semester';
        }

        $startDate = trim((string) ($period['start_date'] ?? ''));
        if ($startDate !== '') {
            try {
                return Carbon::parse($startDate)->format('F Y');
            } catch (\Throwable $e) {
                // Fall through to the generic fallback.
            }
        }

        return 'Selected month';
    }

    public function formatRange(array $report): string
    {
        $period = $report['period'] ?? [];

        // logger()->debug('ReportPeriodFormatter formatRange input', [
        //     'preferred_period' => $report['preferred_period'] ?? null,
        //     'period_type' => $report['period_type'] ?? null,
        //     'start_date' => $period['start_date'] ?? null,
        //     'end_date' => $period['end_date'] ?? null,
        //     'period' => $period,
        //     'report' => $report,
        // ]);

        $startDate = trim((string) ($period['start_date'] ?? ''));
        $endDate = trim((string) ($period['end_date'] ?? ''));

        if ($startDate === '' && $endDate === '') {
            return '-';
        }

        if ($startDate === '') {
            return $this->formatDateLabel($endDate);
        }

        if ($endDate === '' || $startDate === $endDate) {
            return $this->formatDateLabel($startDate);
        }

        return $this->formatDateLabel($startDate) . ' - ' . $this->formatDateLabel($endDate);
    }

    private function formatDateLabel(string $value): string
    {
        try {
            return Carbon::parse($value)->format('j M Y');
        } catch (\Throwable $e) {
            return trim($value);
        }
    }

    private function preferredPeriod(array $report): string
    {
        $candidates = [
            $report['preferred_period'] ?? null,
            $report['user']['preferred_period'] ?? null,
            $report['auth_user']['preferred_period'] ?? null,
            $report['account']['preferred_period'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
