<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CSV downloads of the Items OB & CB and Consolidated reports, shared by
 * the app (StockDetailsController) and the admin console
 * (Admin\AdminReportController) so both export the same file. Moved here
 * unchanged from StockDetailsController (2026-11-24).
 */
final class ReportCsv
{
    /** Items OB & CB: one header row from the first record, then every record. */
    public static function itemsObcb(array $result, string $filename = 'history_items_obcb.csv'): StreamedResponse
    {
        // getItemsObcbReport returns the rows under `records`. This used to read
        // `details.records`, which never exists, so every export was an empty
        // file (fixed 2026-11-24, app and console).
        $records = collect($result['records'] ?? $result['details']['records'] ?? [])->values()->all();

        return response()->streamDownload(function () use ($records) {
            $file = fopen('php://output', 'w');
            if (count($records) > 0) {
                fputcsv($file, array_keys((array) $records[0]));
                foreach ($records as $row) {
                    fputcsv($file, (array) $row);
                }
            }
            fclose($file);
        }, $filename);
    }

    /** Consolidated: the OUT records, then the IN records, each with its header. */
    public static function consolidated(array $result, string $filename = 'consolidated_report.csv'): StreamedResponse
    {
        $outRecords = $result['out_details']['records'] ?? [];
        $inRecords = $result['in_details']['records'] ?? [];

        return response()->streamDownload(function () use ($outRecords, $inRecords) {
            $file = fopen('php://output', 'w');

            if (count($outRecords) > 0) {
                fputcsv($file, ['TYPE']);
                fputcsv($file, ['OUT_RECORDS']);
                fputcsv($file, array_keys((array) $outRecords[0]));
                foreach ($outRecords as $row) {
                    fputcsv($file, (array) $row);
                }
            }

            if (count($inRecords) > 0) {
                fputcsv($file, []);
                fputcsv($file, ['TYPE']);
                fputcsv($file, ['IN_RECORDS']);
                fputcsv($file, array_keys((array) $inRecords[0]));
                foreach ($inRecords as $row) {
                    fputcsv($file, (array) $row);
                }
            }
            fclose($file);
        }, $filename);
    }
}
