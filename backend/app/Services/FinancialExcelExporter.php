<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class FinancialExcelExporter
{
    private const STYLE_TITLE = 1;
    private const STYLE_SUBTITLE = 2;
    private const STYLE_LABEL = 3;
    private const STYLE_KPI = 4;
    private const STYLE_SECTION = 5;
    private const STYLE_TABLE_HEAD = 6;
    private const STYLE_TEXT = 7;
    private const STYLE_CURRENCY = 8;
    private const STYLE_INCOME = 9;
    private const STYLE_EXPENSE = 10;
    private const STYLE_DATE = 11;

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
        $zip->addFromString('xl/theme/theme1.xml', $this->themeXml());
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
  <Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
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
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <fonts count="4">
    <font>
      <sz val="11"/>
      <color rgb="FF111827"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="11"/>
      <color rgb="FF0F172A"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="14"/>
      <color rgb="FFFFFFFF"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="12"/>
      <color rgb="FF0F172A"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="8">
    <fill>
      <patternFill patternType="none"/>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FF0F172A"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FF06B6D4"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFECFDF5"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFFEE2E2"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFFEF3C7"/>
        <bgColor indexed="64"/>
      </patternFill>
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
  <numFmts count="1">
    <numFmt numFmtId="164" formatCode="&quot;RM&quot; #,##0.00"/>
  </numFmts>
  <cellXfs count="12">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="2" fillId="1" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="2" fillId="1" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="3" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private function summarySheetXml(array $report): string
    {
        $rows = [
            [['type' => 's', 'value' => $report['title'] ?? 'Financial Report', 'style' => self::STYLE_TITLE]],
            [['type' => 's', 'value' => 'Transaction list export', 'style' => self::STYLE_SUBTITLE]],
            [['type' => 's', 'value' => 'Period: ' . ($report['period']['label'] ?? 'Selected period'), 'style' => self::STYLE_SUBTITLE]],
            [['type' => 's', 'value' => 'Range: ' . $this->formatDateRange((string) ($report['period']['start_date'] ?? ''), (string) ($report['period']['end_date'] ?? '')), 'style' => self::STYLE_SUBTITLE]],
        ];
        $rows[] = [
            ['type' => 's', 'value' => 'Date', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Type', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Category', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Description', 'style' => self::STYLE_TABLE_HEAD],
            ['type' => 's', 'value' => 'Amount', 'style' => self::STYLE_TABLE_HEAD],
        ];

        foreach ($report['transactions'] ?? [] as $transaction) {
            $rows[] = [
                ['type' => 'n', 'value' => (string) $this->excelDateSerial((string) ($transaction['transaction_date'] ?? '')), 'style' => self::STYLE_DATE],
                ['type' => 's', 'value' => strtoupper((string) ($transaction['type'] ?? '')), 'style' => $this->transactionStyle($transaction['type'] ?? '')],
                ['type' => 's', 'value' => (string) ($transaction['category'] ?? 'Uncategorized'), 'style' => self::STYLE_TEXT],
                ['type' => 's', 'value' => (string) ($transaction['description'] ?? ''), 'style' => self::STYLE_TEXT],
                ['type' => 'n', 'value' => (string) ($transaction['amount'] ?? 0), 'style' => $this->transactionStyle($transaction['type'] ?? '', true)],
            ];
        }

        return $this->worksheetXml($rows, [
            'sheet_view' => 'frozen',
            'freeze_row' => 5,
            'auto_filter' => 'A5:E5',
            'columns' => [
                ['width' => 14],
                ['width' => 12],
                ['width' => 24],
                ['width' => 38],
                ['width' => 16],
            ],
            'merged_ranges' => ['A1:E1'],
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
            $rowHeight = $rowNumber === 1 ? 24 : ($rowNumber <= 4 ? 20 : ($rowNumber === 5 ? 22 : 18));
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

    private function logoBytes(): string
    {
        $path = self::LOGO_PATH;
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function drawingXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <xdr:oneCellAnchor>
    <xdr:from>
      <xdr:col>3</xdr:col>
      <xdr:colOff>0</xdr:colOff>
      <xdr:row>0</xdr:row>
      <xdr:rowOff>0</xdr:rowOff>
    </xdr:from>
    <xdr:ext cx="1143000" cy="1143000"/>
    <xdr:pic>
      <xdr:nvPicPr>
        <xdr:cNvPr id="2" name="SmartBudget Logo"/>
        <xdr:cNvPicPr>
          <a:picLocks noChangeAspect="1"/>
        </xdr:cNvPicPr>
      </xdr:nvPicPr>
      <xdr:blipFill>
        <a:blip r:embed="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>
        <a:stretch>
          <a:fillRect/>
        </a:stretch>
      </xdr:blipFill>
      <xdr:spPr>
        <a:xfrm>
          <a:off x="0" y="0"/>
          <a:ext cx="1143000" cy="1143000"/>
        </a:xfrm>
        <a:prstGeom prst="rect">
          <a:avLst/>
        </a:prstGeom>
      </xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:oneCellAnchor>
</xdr:wsDr>
XML;
    }

    private function drawingRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo.png"/>
</Relationships>
XML;
    }

    private function sheet1RelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML;
    }

    private function themeXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="PFMS">
  <a:themeElements>
    <a:clrScheme name="PFMS">
      <a:dk1><a:sysClr val="windowText" lastClr="0F172A"/></a:dk1>
      <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
      <a:dk2><a:srgbClr val="0F172A"/></a:dk2>
      <a:lt2><a:srgbClr val="F8FAFC"/></a:lt2>
      <a:accent1><a:srgbClr val="06B6D4"/></a:accent1>
      <a:accent2><a:srgbClr val="10B981"/></a:accent2>
      <a:accent3><a:srgbClr val="F59E0B"/></a:accent3>
      <a:accent4><a:srgbClr val="F43F5E"/></a:accent4>
      <a:accent5><a:srgbClr val="38BDF8"/></a:accent5>
      <a:accent6><a:srgbClr val="1E293B"/></a:accent6>
      <a:hlink><a:srgbClr val="0EA5E9"/></a:hlink>
      <a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="PFMS">
      <a:majorFont>
        <a:latin typeface="Aptos"/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos"/>
      </a:minorFont>
    </a:fontScheme>
    <a:fmtScheme name="PFMS">
      <a:fillStyleLst>
        <a:solidFill><a:schemeClr val="accent1"/></a:solidFill>
      </a:fillStyleLst>
      <a:lnStyleLst>
        <a:ln w="9525"><a:solidFill><a:schemeClr val="accent1"/></a:solidFill></a:ln>
      </a:lnStyleLst>
      <a:effectStyleLst>
        <a:effectStyle/>
      </a:effectStyleLst>
      <a:bgFillStyleLst>
        <a:solidFill><a:schemeClr val="lt2"/></a:solidFill>
      </a:bgFillStyleLst>
    </a:fmtScheme>
  </a:themeElements>
</a:theme>
XML;
    }

    private function transactionStyle(string $type, bool $isAmount = false): int
    {
        $type = strtolower(trim($type));
        if ($isAmount) {
            return $type === 'income' ? self::STYLE_INCOME : self::STYLE_EXPENSE;
        }

        return $type === 'income' ? self::STYLE_INCOME : self::STYLE_EXPENSE;
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
            return Carbon::parse($value)->format('j M Y');
        } catch (\Throwable $e) {
            return trim($value);
        }
    }

    private function excelDateSerial(string $value): int
    {
        try {
            $date = Carbon::parse($value, 'UTC')->startOfDay();
            $base = Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC');

            return (int) $base->diffInDays($date);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function toArray($value): array
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->all();
        }

        return is_array($value) ? $value : [];
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
