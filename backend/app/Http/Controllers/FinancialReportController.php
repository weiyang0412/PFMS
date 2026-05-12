<?php

namespace App\Http\Controllers;

use App\Services\FinancialExcelExporter;
use App\Services\FinancialPdfExporter;
use App\Services\FinancialReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinancialReportController extends Controller
{
    /** @var FinancialReportBuilder */
    private $builder;

    /** @var FinancialPdfExporter */
    private $pdfExporter;

    /** @var FinancialExcelExporter */
    private $excelExporter;

    public function __construct(
        FinancialReportBuilder $builder,
        FinancialPdfExporter $pdfExporter,
        FinancialExcelExporter $excelExporter
    ) {
        $this->builder = $builder;
        $this->pdfExporter = $pdfExporter;
        $this->excelExporter = $excelExporter;
    }

    public function index(Request $request)
    {
        return response()->json($this->builder->build($request));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'excel'])],
        ]);

        $report = $this->builder->build($request);
        $baseName = $this->buildFilename($report);

        if ($validated['format'] === 'pdf') {
            return $this->pdfExporter->download($report, $baseName . '.pdf');
        }

        return $this->excelExporter->download($report, $baseName . '.xlsx');
    }

    private function buildFilename(array $report): string
    {
        $period = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($report['period']['label'] ?? 'report'));
        $period = trim((string) $period, '-');
        $period = $period !== '' ? $period : 'report';

        return 'financial-report-' . strtolower($period);
    }
}
