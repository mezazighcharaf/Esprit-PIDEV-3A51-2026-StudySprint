<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * @param string   $filename  e.g. "users_export.csv"
     * @param string[] $headers   column headers
     * @param iterable $rows      each row is an array of values
     */
    public function export(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
