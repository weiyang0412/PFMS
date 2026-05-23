<?php

namespace App\Services;

use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class FinancialExcelExporter
{
    private const STYLE_TITLE = 1;
    private const STYLE_SUBTITLE = 2;
    private const STYLE_TABLE_HEAD = 6;
    private const STYLE_TEXT = 7;
    private const STYLE_AMOUNT_INCOME = 8;
    private const STYLE_AMOUNT_EXPENSE = 9;
    private const STYLE_DATE = 10;

    public function download(array $report, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'pfms_xlsx_');
        if ($path === false) {
            abort(500, 'Unable to create a temporary file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create the workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->summarySheetXml($report));
        $zip->close();

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private function rootRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Transactions" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="&quot;RM&quot; #,##0.00"/>
    <numFmt numFmtId="165" formatCode="d mmm yyyy"/>
  </numFmts>
  <fonts count="4">
    <font>
      <sz val="11"/>
      <color rgb="FF111827"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <sz val="11"/>
      <color rgb="FF0F172A"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <sz val="14"/>
      <color rgb="FFFFFFFF"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <sz val="12"/>
      <color rgb="FF0F172A"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="7">
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="gray125"/>
    </fill>
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="none"/>
    </fill>
  </fills>
  <borders count="3">
    <border>
      <left/>
      <right/>
      <top/>
      <bottom/>
      <diagonal/>
    </border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
      <diagonal/>
    </border>
    <border>
      <left style="thin"><color rgb="FF0F172A"/></left>
      <right style="thin"><color rgb="FF0F172A"/></right>
      <top style="thin"><color rgb="FF0F172A"/></top>
      <bottom style="thin"><color rgb="FF0F172A"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="11">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
  <dxfs count="0"/>
  <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>
XML;
    }

    private function summarySheetXml(array $report): string
    {
        $periodFormatter = new ReportPeriodFormatter();
        $rows = [
            [['type' => 's', 'value' => $report['title'] ?? 'Financial Report', 'style' => self::STYLE_TITLE]],
        ];
        $mergedRanges = ['A1:E1'];

        if ($periodFormatter->isSemester($report)) {
            $rows[] = [
                ['type' => 's', 'value' => 'Period: ' . $periodFormatter->format($report), 'style' => self::STYLE_SUBTITLE],
                ['type' => 's', 'value' => '', 'style' => self::STYLE_SUBTITLE],
                ['type' => 's', 'value' => '', 'style' => self::STYLE_SUBTITLE],
                ['type' => 's', 'value' => 'Range: ' . $periodFormatter->formatRange($report), 'style' => self::STYLE_SUBTITLE],
                ['type' => 's', 'value' => '', 'style' => self::STYLE_SUBTITLE],
            ];
            $mergedRanges[] = 'A2:C2';
            $mergedRanges[] = 'D2:E2';
        } else {
            $rows[] = [
                ['type' => 's', 'value' => 'Period: ' . $periodFormatter->format($report), 'style' => self::STYLE_SUBTITLE],
            ];
            $mergedRanges[] = 'A2:E2';
        }
        $rows[] = [
            ['type' => 's', 'value' => 'Date', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Type', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Category', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Description', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Amount', 'style' => self::STYLE_TABLE_HEAD],
        ];

        foreach ($report['transactions'] ?? [] as $transaction) {
            $rows[] = [
                ['type' => 's', 'value' => $this->formatDateLabel((string) ($transaction['transaction_date'] ?? '')), 'style' => self::STYLE_DATE],
                ['type' => 's', 'value' => strtoupper((string) ($transaction['type'] ?? '')), 'style' => self::STYLE_TEXT],
                ['type' => 's', 'value' => (string) ($transaction['category'] ?? 'Uncategorized'), 'style' => self::STYLE_TEXT],
                ['type' => 's', 'value' => (string) ($transaction['description'] ?? ''), 'style' => self::STYLE_TEXT],
                ['type' => 'n', 'value' => (string) ($transaction['amount'] ?? 0), 'style' => $this->amountStyle($transaction['type'] ?? '')],
            ];
        }

        return $this->worksheetXml($rows, [
            'sheet_view' => 'frozen',
            'freeze_row' => 3,
            'auto_filter' => 'A3:E3',
            'columns' => [
                ['width' => 14],
                ['width' => 12],
                ['width' => 24],
                ['width' => 38],
                ['width' => 16],
            ],
            'merged_ranges' => $mergedRanges,
        ]);
    }

    private function worksheetXml(array $rows, array $options = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if (!empty($options['sheet_view']) && ($options['freeze_row'] ?? 0) > 0) {
            $row = (int) $options['freeze_row'];
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $row . '" topLeftCell="A' . ($row + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }
        if (!empty($options['columns'])) {
            $xml .= '<cols>';
            $colIndex = 1;
            foreach ($options['columns'] as $column) {
                $width = (float) ($column['width'] ?? 15);
                $xml .= '<col min="' . $colIndex . '" max="' . $colIndex . '" width="' . $width . '" customWidth="1"/>';
                $colIndex++;
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            if ($cells === []) {
                continue;
            }
            $rowNumber = $rowIndex + 1;
            $rowHeight = $rowNumber === 1 ? 24 : ($rowNumber === 2 ? 20 : ($rowNumber === 3 ? 22 : 18));
            $xml .= '<row r="' . $rowNumber . '" ht="' . $rowHeight . '" customHeight="1">';

            foreach ($cells as $cellIndex => $cell) {
                $column = $this->columnName($cellIndex + 1);
                $style = isset($cell['style']) ? ' s="' . (int) $cell['style'] . '"' : '';
                $value = $this->xmlEscape((string) ($cell['value'] ?? ''));

                if (($cell['type'] ?? 's') === 'n') {
                    $xml .= '<c r="' . $column . $rowNumber . '"' . $style . '><v>' . $value . '</v></c>';
                } else {
                    $xml .= '<c r="' . $column . $rowNumber . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $value . '</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        if (!empty($options['merged_ranges'])) {
            $mergeXml = '<mergeCells count="' . count($options['merged_ranges']) . '">';
            foreach ($options['merged_ranges'] as $range) {
                $mergeXml .= '<mergeCell ref="' . $this->xmlEscape((string) $range) . '"/>';
            }
            $mergeXml .= '</mergeCells>';
            $xml = str_replace('</worksheet>', $mergeXml . '</worksheet>', $xml);
        }

        if (!empty($options['auto_filter'])) {
            $xml = str_replace('</sheetData>', '</sheetData><autoFilter ref="' . $this->xmlEscape((string) $options['auto_filter']) . '"/>', $xml);
        }

        if (!empty($options['drawing'])) {
            $xml = str_replace('</worksheet>', '<drawing r:id="rId1"/></worksheet>', $xml);
        }

        return $xml;
    }

    private function amountStyle(string $type): int
    {
        $type = strtolower(trim($type));
        return $type === 'income' ? self::STYLE_AMOUNT_INCOME : self::STYLE_AMOUNT_EXPENSE;
    }

    private function formatPeriodLabel(array $report): string
    {
        $label = trim((string) ($report['period']['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $startDate = trim((string) ($report['period']['start_date'] ?? ''));
        if ($startDate === '') {
            return 'Selected period';
        }

        try {
            return Carbon::parse($startDate)->format('F Y');
        } catch (\Throwable $e) {
            return 'Selected period';
        }
    }

    private function formatDateLabel(string $value): string
    {
        try {
            return Carbon::parse($value)->format('j M Y');
        } catch (\Throwable $e) {
            return trim($value);
        }
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
