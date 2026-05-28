<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialPdfExporter
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;

    private const COLOR_DARK = [15, 23, 42];
    private const COLOR_DARKER = [15, 23, 42];
    private const COLOR_TEXT = [15, 23, 42];
    private const COLOR_HEADER_TEXT = [255, 255, 255];
    private const COLOR_HEADER_MUTED = [203, 213, 225];
    private const COLOR_MUTED = [100, 116, 139];
    private const COLOR_BORDER = [226, 232, 240];
    private const COLOR_SURFACE = [241, 245, 249];
    private const COLOR_PANEL = [255, 255, 255];
    private const COLOR_PANEL_SUBTLE = [248, 250, 252];
    private const COLOR_CYAN = [6, 182, 212];
    private const COLOR_EMERALD = [16, 185, 129];
    private const COLOR_ROSE = [244, 63, 94];
    private const COLOR_AMBER = [245, 158, 11];
    private const COLOR_GREEN_BG = [236, 253, 245];
    private const COLOR_ROSE_BG = [255, 241, 242];
    private const COLOR_AMBER_BG = [255, 251, 235];
    private const COLOR_CYAN_BG = [236, 254, 255];
    private const LOGO_PATH = __DIR__ . '/../../../../frontend/src/assets/logo.png';

    public function download(array $report, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'pfms_pdf_');
        if ($path === false) {
            abort(500, 'Unable to create a temporary file.');
        }

        $pdf = $this->buildPdf($report);
        file_put_contents($path, $pdf);

        return response()
            ->download($path, $filename, ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    private function buildPdf(array $report): string
    {
        $logo = $this->buildLogoImageObject(self::COLOR_DARKER);
        $pages = $this->buildPages($report);
        if ($pages === []) {
            $pages = [[]];
        }
        $pageObjectIds = [];
        $contentObjectIds = [];
        $nextObjectId = 6;
        foreach ($pages as $_page) {
            $pageObjectIds[] = $nextObjectId;
            $contentObjectIds[] = $nextObjectId + 1;
            $nextObjectId += 2;
        }
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [' . implode(' ', array_map(
                fn ($pageObjectId) => $pageObjectId . ' 0 R',
                $pageObjectIds
            )) . '] /Count ' . count($pages) . ' >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            5 => $this->buildImageObject($logo['bytes'], $logo['width'], $logo['height']),
        ];

        foreach ($pages as $pageIndex => $page) {
            $pageObjectId = $pageObjectIds[$pageIndex];
            $contentObjectId = $contentObjectIds[$pageIndex];
            $objects[$contentObjectId] = $this->buildStreamObject($this->buildContentStream($page, $pageIndex + 1, count($pages)));
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Im5 5 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    private function buildPages(array $report): array
    {
        $transactions = $this->toArray($report['transactions'] ?? []);
        $pages = [
            [
                'type' => 'cover',
                'report' => $report,
                'transactions' => array_slice($transactions, 0, 30),
            ],
        ];

        $remainingTransactions = array_slice($transactions, 30);
        foreach (array_chunk($remainingTransactions, 30) as $chunk) {
            $pages[] = [
                'type' => 'transactions',
                'report' => $report,
                'transactions' => $chunk,
            ];
        }

        return $pages;
    }

    private function buildContentStream(array $page, int $pageNumber, int $pageCount): string
    {
        $stream = "q\n";
        $stream .= $this->drawRect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, self::COLOR_SURFACE);

        if (($page['type'] ?? '') === 'cover') {
            $stream .= $this->drawHeaderBand($page['report'] ?? []);
            $stream .= $this->drawTransactionsTable($page['transactions'] ?? [], 36, 648, 30);
        } else {
            $stream .= $this->drawTransactionsTable($page['transactions'] ?? [], 36, 742, 30);
        }
        $stream .= $this->drawFooter($pageNumber, $pageCount);
        $stream .= "Q";

        return $stream;
    }

    private function buildStreamObject(string $stream): string
    {
        return "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    private function drawHeaderBand(array $report): string
    {
        $periodFormatter = new ReportPeriodFormatter();
        $period = $this->escapePdfText($periodFormatter->format($report));
        $range = $this->escapePdfText($periodFormatter->formatRange($report));
        $generated = $this->escapePdfText($this->formatDateTime((string) ($report['generated_at'] ?? now()->toIso8601String())));
        $isSemester = $periodFormatter->isSemester($report);

        $stream = '';
        $stream .= $this->drawRect(0, 670, self::PAGE_WIDTH, 172, self::COLOR_DARKER);
        $stream .= $this->drawText(36, 792, 8, 'F1', 'TRANSACTION LIST', self::COLOR_HEADER_MUTED);
        $stream .= $this->drawText(36, 770, 20, 'F2', 'Transactions', self::COLOR_HEADER_TEXT);
        $stream .= $this->drawText(36, 744, 10, 'F1', 'Period: ' . $period, self::COLOR_HEADER_MUTED);
        if ($isSemester) {
            $stream .= $this->drawText(36, 728, 9, 'F1', 'Range: ' . $range, self::COLOR_HEADER_MUTED);
            $stream .= $this->drawText(36, 714, 10, 'F1', 'Generated ' . $generated, self::COLOR_HEADER_MUTED);
        } else {
            $stream .= $this->drawText(36, 728, 10, 'F1', 'Generated ' . $generated, self::COLOR_HEADER_MUTED);
        }
        $stream .= $this->drawImage(5, 452, 720, 96, 96);

        return $stream;
    }

    private function drawCoverSummary(array $report, array $summary): string
    {
        $cards = [
            ['label' => 'Transactions', 'value' => (string) ($summary['transaction_count'] ?? 0), 'fill' => self::COLOR_CYAN_BG, 'text' => self::COLOR_DARKER, 'accent' => self::COLOR_CYAN],
            ['label' => 'Income', 'value' => $this->formatAmount((float) ($summary['income'] ?? 0)), 'fill' => self::COLOR_GREEN_BG, 'text' => self::COLOR_DARKER, 'accent' => self::COLOR_EMERALD],
            ['label' => 'Expense', 'value' => $this->formatAmount((float) ($summary['expense'] ?? 0)), 'fill' => self::COLOR_ROSE_BG, 'text' => self::COLOR_DARKER, 'accent' => self::COLOR_ROSE],
            ['label' => 'Net', 'value' => $this->formatAmount((float) ($summary['net'] ?? 0)), 'fill' => self::COLOR_AMBER_BG, 'text' => self::COLOR_DARKER, 'accent' => self::COLOR_AMBER],
        ];

        $stream = '';
        $x = 36;
        foreach ($cards as $card) {
            $stream .= $this->drawRoundedBox($x, 560, 122, 74, self::COLOR_PANEL_SUBTLE);
            $stream .= $this->drawBorderBox($x, 560, 122, 74, self::COLOR_BORDER);
            $stream .= $this->drawText($x + 12, 615, 8, 'F1', $card['label'], self::COLOR_MUTED);
            $stream .= $this->drawText($x + 12, 592, 16, 'F2', $card['value'], $card['accent']);
            $x += 130;
        }

        $stream .= $this->drawRoundedBox(36, 430, 512, 114, self::COLOR_PANEL);
        $stream .= $this->drawBorderBox(36, 430, 512, 114, self::COLOR_BORDER);
        $stream .= $this->drawText(50, 522, 8, 'F1', 'RECENT TRANSACTIONS', self::COLOR_HEADER_MUTED);
        $stream .= $this->drawText(50, 506, 13, 'F2', 'Latest records', self::COLOR_DARKER);

        $transactions = $this->toArray($report['transactions'] ?? []);
        $topTransactions = array_slice($transactions, 0, 4);
        if ($topTransactions === []) {
            $stream .= $this->drawText(50, 476, 10, 'F1', 'No transaction data for this period.', self::COLOR_MUTED);
            $stream .= $this->drawText(50, 460, 8, 'F1', 'Add transactions to populate the list.', self::COLOR_MUTED);

            return $stream;
        }

        $tableX = 50;
        $tableY = 486;
        $dateX = $tableX;
        $typeX = $tableX + 95;
        $descriptionX = $tableX + 145;
        $amountX = $tableX + 434;
        $stream .= $this->drawText($dateX, $tableY, 8, 'F2', 'Date', self::COLOR_HEADER_MUTED);
        $stream .= $this->drawText($typeX, $tableY, 8, 'F2', 'Type', self::COLOR_HEADER_MUTED);
        $stream .= $this->drawText($descriptionX, $tableY, 8, 'F2', 'Description', self::COLOR_HEADER_MUTED);
        $stream .= $this->drawText($amountX, $tableY, 8, 'F2', 'Amount', self::COLOR_HEADER_MUTED);

        $y = 470;
        foreach ($topTransactions as $transaction) {
            $type = strtolower((string) ($transaction['type'] ?? ''));
            $typeColor = $type === 'income' ? self::COLOR_EMERALD : self::COLOR_ROSE;
            $date = $this->formatDateLabel((string) ($transaction['transaction_date'] ?? ''));
            $description = $this->limit($this->safeText((string) ($transaction['description'] ?? '')), 24);
            $amount = $this->formatAmount((float) ($transaction['amount'] ?? 0));

            $stream .= $this->drawText($dateX, $y, 8, 'F1', $date, self::COLOR_MUTED);
            $stream .= $this->drawText($typeX, $y, 8, 'F2', strtoupper($type === 'income' ? 'IN' : 'OUT'), $typeColor);
            $stream .= $this->drawText($descriptionX, $y, 9, 'F1', $description, self::COLOR_DARK);
            $stream .= $this->drawText($amountX, $y, 9, 'F1', $amount, $typeColor);
            $stream .= $this->drawBorderLine($tableX, $y - 8, 472, 12, self::COLOR_BORDER);
            $y -= 22;
        }

        return $stream;
    }

    private function drawTransactionsTable(array $transactions, float $x, float $y, int $maxRows = 18): string
    {
        $stream = '';
        $rowHeight = 18;
        $tableWidth = 523;
        $dateX = $x + 10;
        $typeX = $x + 68;
        $categoryX = $x + 124;
        $descriptionX = $x + 230;
        $amountX = $x + 447;

        $tableY = $y - 12;
        $stream .= $this->drawRect($x, $tableY, $tableWidth, 22, self::COLOR_DARK);
        $stream .= $this->drawText($dateX, $tableY + 7, 8, 'F2', 'Date', self::COLOR_HEADER_TEXT);
        $stream .= $this->drawText($typeX, $tableY + 7, 8, 'F2', 'Type', self::COLOR_HEADER_TEXT);
        $stream .= $this->drawText($categoryX, $tableY + 7, 8, 'F2', 'Category', self::COLOR_HEADER_TEXT);
        $stream .= $this->drawText($descriptionX, $tableY + 7, 8, 'F2', 'Description', self::COLOR_HEADER_TEXT);
        $stream .= $this->drawText($amountX, $tableY + 7, 8, 'F2', 'Amount', self::COLOR_HEADER_TEXT);

        $currentY = $tableY - 18;
        foreach (array_slice($transactions, 0, $maxRows) as $index => $transaction) {
            $fill = $index % 2 === 0 ? self::COLOR_PANEL_SUBTLE : self::COLOR_PANEL;
            $stream .= $this->drawRect($x, $currentY - 4, $tableWidth, $rowHeight, $fill);
            $stream .= $this->drawBorderLine($x, $currentY - 4, $tableWidth, $rowHeight, self::COLOR_BORDER);

            $type = strtolower((string) ($transaction['type'] ?? ''));
            $typeColor = $type === 'income' ? self::COLOR_EMERALD : self::COLOR_ROSE;
            $stream .= $this->drawText($dateX, $currentY + 3, 8, 'F1', $this->formatDateLabel((string) ($transaction['transaction_date'] ?? '')), self::COLOR_MUTED);
            $stream .= $this->drawText($typeX, $currentY + 3, 8, 'F2', strtoupper($type === 'income' ? 'IN' : 'OUT'), $typeColor);
            $stream .= $this->drawText($categoryX, $currentY + 3, 8, 'F1', $this->limit($this->safeText((string) ($transaction['category'] ?? 'Uncategorized')), 15), self::COLOR_DARK);
            $stream .= $this->drawText($descriptionX, $currentY + 3, 8, 'F1', $this->limit($this->safeText((string) ($transaction['description'] ?? '')), 29), self::COLOR_DARK);
            $stream .= $this->drawText($amountX, $currentY + 3, 8, 'F1', $this->formatAmount((float) ($transaction['amount'] ?? 0)), $typeColor);

            $currentY -= $rowHeight;
        }

        return $stream;
    }

    private function drawSectionHeader(string $title, string $subtitle, float $x, float $y): string
    {
        return $this->drawText($x, $y, 13, 'F2', $title, self::COLOR_DARKER)
            . $this->drawText($x, $y - 14, 8, 'F1', $subtitle, self::COLOR_MUTED);
    }

    private function drawFooter(int $pageNumber, int $pageCount): string
    {
        $stream = '';
        $stream .= $this->drawRect(0, 0, self::PAGE_WIDTH, 34, self::COLOR_PANEL);
        $stream .= $this->drawBorderLine(0, 0, self::PAGE_WIDTH, 34, self::COLOR_BORDER);
        $stream .= $this->drawText(36, 14, 8, 'F1', 'SMARTBUDGET financial export', self::COLOR_MUTED);
        $stream .= $this->drawText(480, 14, 8, 'F1', 'Page ' . $pageNumber . ' / ' . $pageCount, self::COLOR_MUTED);

        return $stream;
    }

    private function drawText(float $x, float $y, float $size, string $font, string $text, array $rgb): string
    {
        return sprintf(
            "q\n%.3f %.3f %.3f rg\nBT\n/%s %.1f Tf\n%.1f %.1f Td\n(%s) Tj\nET\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $font,
            $size,
            $x,
            $y,
            $this->escapePdfText($this->toPdfText($text))
        );
    }

    private function drawImage(int $objectId, float $x, float $y, float $w, float $h): string
    {
        return sprintf("q\n%.1f 0 0 %.1f %.1f %.1f cm\n/Im%d Do\nQ\n", $w, $h, $x, $y, $objectId);
    }

    private function drawRect(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return sprintf(
            "q\n%.3f %.3f %.3f rg\n%.1f %.1f %.1f %.1f re\nf\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x,
            $y,
            $w,
            $h
        );
    }

    private function drawBorderBox(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return sprintf(
            "q\n%.3f %.3f %.3f RG\n1 w\n%.1f %.1f %.1f %.1f re\nS\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x,
            $y,
            $w,
            $h
        );
    }

    private function drawBorderLine(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return sprintf(
            "q\n%.3f %.3f %.3f RG\n0.5 w\n%.1f %.1f %.1f %.1f re\nS\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x,
            $y,
            $w,
            $h
        );
    }

    private function drawRoundedBox(float $x, float $y, float $w, float $h, array $rgb): string
    {
        $r = 8;
        $x2 = $x + $w;
        $y2 = $y + $h;

        return sprintf(
            "q\n%.3f %.3f %.3f rg\n%.1f %.1f m\n%.1f %.1f l\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f l\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f l\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f l\n%.1f %.1f %.1f %.1f %.1f %.1f c\nf\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x + $r,
            $y,
            $x2 - $r,
            $y,
            $x2,
            $y,
            $x2,
            $y,
            $x2,
            $y + $r,
            $x2,
            $y2 - $r,
            $x2,
            $y2 - $r,
            $x2,
            $y2,
            $x2 - $r,
            $y2,
            $x + $r,
            $y2,
            $x,
            $y2,
            $x,
            $y2,
            $x,
            $y2 - $r,
            $x,
            $y + $r,
            $x,
            $y + $r,
            $x,
            $y,
            $x + $r,
            $y
        );
    }

    private function drawCircle(float $x, float $y, float $radius, array $rgb): string
    {
        $k = 0.5522847498;
        $c = $radius * $k;

        return sprintf(
            "q\n%.3f %.3f %.3f rg\n%.1f %.1f m\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f %.1f %.1f %.1f %.1f c\n%.1f %.1f %.1f %.1f %.1f %.1f c\nf\nQ\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x + $radius,
            $y,
            $x + $radius,
            $y + $c,
            $x + $c,
            $y + $radius,
            $x,
            $y + $radius,
            $x,
            $y + $radius,
            $x - $c,
            $y + $radius,
            $x - $radius,
            $y + $c,
            $x - $radius,
            $y,
            $x - $radius,
            $y - $c,
            $x - $c,
            $y - $radius,
            $x,
            $y - $radius,
            $x,
            $y - $radius,
            $x + $c,
            $y - $radius,
            $x + $radius,
            $y - $c,
            $x + $radius,
            $y
        );
    }

    private function drawBar(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return $this->drawRoundedBox($x, $y, $w, $h, $rgb);
    }

    private function formatAmount(float $amount): string
    {
        $formatted = 'RM ' . number_format(abs($amount), 2, '.', ',');

        return $amount < 0 ? '-' . $formatted : $formatted;
    }

    private function formatDateTime(string $value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function formatDateRange(string $startDate, string $endDate): string
    {
        $start = $this->formatDateLabel($startDate);
        $end = $this->formatDateLabel($endDate);

        if ($start === '' && $end === '') {
            return '-';
        }

        if ($start === '') {
            return $end;
        }

        if ($end === '' || $start === $end) {
            return $start;
        }

        return $start . ' - ' . $end;
    }

    private function formatDateLabel(string $value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format('j M Y');
        } catch (\Throwable $e) {
            return trim($value);
        }
    }

    private function limit(string $value, int $maxLength): string
    {
        $normalized = $this->safeText($value);
        if ($maxLength <= 0) {
            return '';
        }

        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return mb_strimwidth($normalized, 0, $maxLength - 1, '…');
    }

    private function safeText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        return $this->toPdfText($value);
    }

    private function buildLogoImageObject(array $backgroundRgb): array
    {
        $path = self::LOGO_PATH;
        if (!is_file($path)) {
            return ['bytes' => '', 'width' => 1, 'height' => 1];
        }

        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return ['bytes' => '', 'width' => 1, 'height' => 1];
        }

        $width = imagesx($image) ?: 1;
        $height = imagesy($image) ?: 1;

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = imagecolorat($image, $x, $y);
                $color = imagecolorsforindex($image, $index);
                if ((int) ($color['alpha'] ?? 127) < 120) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < 0 || $maxY < 0) {
            $minX = 0;
            $minY = 0;
            $maxX = $width - 1;
            $maxY = $height - 1;
        }

        $padding = 10;
        $cropX = max(0, $minX - $padding);
        $cropY = max(0, $minY - $padding);
        $cropWidth = min($width - $cropX, ($maxX - $minX + 1) + ($padding * 2));
        $cropHeight = min($height - $cropY, ($maxY - $minY + 1) + ($padding * 2));

        $bytes = '';
        for ($y = 0; $y < $cropHeight; $y++) {
            for ($x = 0; $x < $cropWidth; $x++) {
                $srcX = $cropX + $x;
                $srcY = $cropY + $y;
                $index = imagecolorat($image, $srcX, $srcY);
                $color = imagecolorsforindex($image, $index);
                $alpha = 1 - ((int) ($color['alpha'] ?? 0) / 127);
                $r = (int) round(($color['red'] ?? $backgroundRgb[0]) * $alpha + $backgroundRgb[0] * (1 - $alpha));
                $g = (int) round(($color['green'] ?? $backgroundRgb[1]) * $alpha + $backgroundRgb[1] * (1 - $alpha));
                $b = (int) round(($color['blue'] ?? $backgroundRgb[2]) * $alpha + $backgroundRgb[2] * (1 - $alpha));
                $bytes .= chr($r) . chr($g) . chr($b);
            }
        }

        $bytes = gzcompress($bytes, 9);
        imagedestroy($image);

        return ['bytes' => $bytes, 'width' => $cropWidth, 'height' => $cropHeight];
    }

    private function buildImageObject(string $bytes, int $width, int $height): string
    {
        return '<< /Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($bytes) . " >>\nstream\n" . $bytes . "\nendstream";
    }

    private function toArray($value): array
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->all();
        }

        return is_array($value) ? $value : [];
    }

    private function toPdfText(string $value): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        return $converted !== false ? $converted : $value;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
