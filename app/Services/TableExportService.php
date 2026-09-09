<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Universal table export service.
 *
 * Works with two column formats:
 *
 * 1. ob-table column definitions (see ob-table.blade.php)
 *    → use resolveColumns() to convert them to the flat format, honouring ?cols=
 *
 * 2. Flat [label, getter] pairs (for fixed-schema exports like cotisations)
 *    → pass directly to toXlsx() / toCsv()
 *
 * Column pair: [ string $label, callable(mixed $item): mixed $getter ]
 */
class TableExportService
{
    // ── Column resolution ─────────────────────────────────────────────────────

    /**
     * Convert ob-table column definitions to flat export pairs, filtered by ?cols=.
     *
     * @param  array  $columns  ob-table column-definition arrays
     * @param  Request  $request  used to parse the ?cols= query parameter
     * @param  array  $prepend  extra [label, fn($item)] pairs prepended unconditionally
     * @return array [ [string $label, callable $getter], … ]
     */
    public function resolveColumns(array $columns, Request $request, array $prepend = []): array
    {
        $colsParam = trim((string) $request->string('cols'));
        $requested = $colsParam !== '' ? array_flip(explode(',', $colsParam)) : null;

        $result = $prepend;

        foreach ($columns as $col) {
            if (! ($col['exportable'] ?? true)) {
                continue;
            }
            if ($col['alwaysVisible'] ?? false) {
                // e.g. the 'name' html column — always shown, but exported via $prepend instead
                continue;
            }

            $key = $col['key'];
            if ($requested !== null && ! isset($requested[$key])) {
                continue;
            }

            $getter = $this->resolveGetter($col);
            $result[] = [$col['exportLabel'] ?? $col['label'], $getter];
        }

        return $result;
    }

    // ── XLSX export ───────────────────────────────────────────────────────────

    /**
     * Stream an XLSX file as a download response.
     *
     * @param  array  $columns  [ [label, getter], … ]
     * @param  string  $filename  Without extension
     * @param  array  $options  Formatting options:
     *                          sheetTitle   string  (default: 'Export')
     *                          headerRgb    string  6-char hex (default: 'DDEEFF')
     *                          freezeHeader bool    Freeze row 2 (default: false)
     *                          zoomScale    int     Sheet zoom % (default: 100)
     *                          repeatHeader bool    Repeat header row on print (default: false)
     */
    public function toXlsx(array $columns, iterable $items, string $filename, array $options = []): StreamedResponse
    {
        $sheetTitle = $options['sheetTitle'] ?? 'Export';
        $headerRgb = $options['headerRgb'] ?? 'DDEEFF';
        $freezeHeader = $options['freezeHeader'] ?? false;
        $zoomScale = (int) ($options['zoomScale'] ?? 100);
        $repeatHeader = $options['repeatHeader'] ?? false;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $colCount = count($columns);

        // Header row
        foreach ($columns as $i => [$label]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($letter.'1', $label);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
            $sheet->getStyle($letter.'1')->getFont()->setBold(true);
        }

        if ($colCount > 0) {
            $range = 'A1:'.Coordinate::stringFromColumnIndex($colCount).'1';
            $sheet->getStyle($range)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($headerRgb);
        }

        if ($freezeHeader) {
            $sheet->freezePane('A2');
        }
        if ($zoomScale !== 100) {
            $sheet->getSheetView()->setZoomScale($zoomScale);
        }
        if ($repeatHeader && $colCount > 0) {
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }

        // Data rows
        $row = 2;
        foreach ($items as $item) {
            $rowData = [];
            foreach ($columns as [, $getter]) {
                $rowData[] = $getter($item);
            }
            $sheet->fromArray($rowData, null, 'A'.$row);
            $row++;
        }

        return response()->streamDownload(
            function () use ($spreadsheet) {
                IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
            },
            $filename.'.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    // ── CSV export ────────────────────────────────────────────────────────────

    /**
     * Stream a UTF-8 CSV file (with BOM for Excel compatibility) as a download response.
     *
     * @param  array  $columns  [ [label, getter], … ]
     * @param  string  $filename  Without extension
     * @param  string  $sep  Column separator (default: ';')
     */
    public function toCsv(array $columns, iterable $items, string $filename, string $sep = ';'): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($columns, $items, $sep) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM so Excel opens the file with the correct encoding
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, array_column($columns, 0), $sep);
                foreach ($items as $item) {
                    $row = [];
                    foreach ($columns as [, $getter]) {
                        $row[] = $getter($item);
                    }
                    fputcsv($handle, $row, $sep);
                }
                fclose($handle);
            },
            $filename.'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Resolve the export getter for a single ob-table column definition.
     * Applies automatic type-based formatting for date and badge columns
     * when no explicit exportValue is provided.
     */
    private function resolveGetter(array $col): callable
    {
        if (isset($col['exportValue'])) {
            return $col['exportValue'];
        }

        $type = $col['type'] ?? 'text';
        $value = $col['value'];

        if ($type === 'date') {
            return static function ($item) use ($value) {
                $v = $value($item);
                if ($v === null) {
                    return '';
                }

                return ($v instanceof Carbon ? $v : Carbon::parse($v))->format('d/m/Y');
            };
        }

        if ($type === 'badge' && isset($col['badgeMap'])) {
            $map = $col['badgeMap'];

            return static fn ($item) => $map[$value($item)][0] ?? $value($item);
        }

        return $value;
    }
}
