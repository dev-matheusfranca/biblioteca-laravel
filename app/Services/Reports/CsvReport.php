<?php

namespace App\Services\Reports;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvReport
{
    /**
     * @param  list<string>  $headers
     * @param  callable(): iterable<array-key, list<int|string|null>>  $rows
     * @param  array<string, string>  $responseHeaders
     */
    public function download(string $filename, array $headers, callable $rows, array $responseHeaders = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Não foi possível iniciar a exportação.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map($this->safe(...), $headers), ';', '"', '');
            foreach ($rows() as $row) {
                fputcsv($output, array_map($this->safe(...), $row), ';', '"', '');
            }
            fclose($output);
        }, $filename, array_merge([
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $responseHeaders));
    }

    private function safe(int|string|null $value): string
    {
        $text = (string) ($value ?? '');
        if (preg_match('/^[\x09\x0A\x0D]/', $text) === 1
            || preg_match('/^(?:[\x00-\x20\x7F]|\p{Z})*[=+\-@]/u', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
