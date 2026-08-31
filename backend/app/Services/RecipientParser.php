<?php

namespace App\Services;

class RecipientParser
{
    /**
     * Normalize a phone number: keep digits, optionally a leading '+'.
     * Returns null when the value is empty / too short to be a real number.
     */
    public static function normalizeNumber(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $leadingPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D/', '', $value);

        if ($digits === '' || $digits === null || strlen($digits) < 7) {
            return null;
        }

        return $leadingPlus ? '+'.$digits : $digits;
    }

    /**
     * Parse a CSV/XLSX(by dropping to plain CSV) file uploaded by the user.
     * Accepts columns named: number/phone/mobile/telefono/... and name/nombre/...
     *
     * @return array<int, array{number: string, name: string|null}>
     */
    public static function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        while (($raw = fgetcsv($handle, 0, self::detectDelimiter($filePath))) !== false) {
            $row = array_map(fn ($cell) => trim((string) $cell), $raw);
            $row = array_values(array_filter($row, fn ($cell) => $cell !== ''));

            if (count($row) > 0) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        if (count($rows) === 0) {
            return [];
        }

        $header = $rows[0];
        $hasHeader = self::looksLikeHeader($header);

        [$numberIndex, $nameIndex] = $hasHeader
            ? self::detectColumns($header)
            : [0, 1];

        $recipients = [];
        $data = $hasHeader ? array_slice($rows, 1) : $rows;

        foreach ($data as $row) {
            $number = self::normalizeNumber($row[$numberIndex] ?? '');
            if ($number === null) {
                continue;
            }

            $name = null;
            if ($nameIndex !== null && isset($row[$nameIndex])) {
                $name = trim((string) $row[$nameIndex]) !== '' ? (string) $row[$nameIndex] : null;
            }

            $recipients[] = ['number' => $number, 'name' => $name];
        }

        return $recipients;
    }

    /**
     * @param  array<int, string>  $row
     */
    private static function looksLikeHeader(array $row): bool
    {
        $numberAliases = ['number', 'phone', 'phone_number', 'phonenumber', 'mobile', 'telefono', 'celular', 'whatsapp', 'no', '号码', '手机号'];

        return (bool) collect($row)->first(fn ($cell) => in_array(strtolower($cell), $numberAliases, true));
    }

    /**
     * @param  array<int, string>  $header
     * @return array{0: int, 1: int|null}
     */
    private static function detectColumns(array $header): array
    {
        $numberAliases = ['number', 'phone', 'phone_number', 'phonenumber', 'mobile', 'telefono', 'celular', 'whatsapp', 'no', '号码', '手机号'];
        $nameAliases = ['name', 'full_name', 'nombre', 'nome', 'customer', 'client', '姓名', '名字'];

        $numberIndex = 0;
        $nameIndex = null;

        foreach ($header as $index => $cell) {
            $cell = strtolower(trim((string) $cell));

            if (in_array($cell, $numberAliases, true)) {
                $numberIndex = $index;
            }

            if (in_array($cell, $nameAliases, true)) {
                $nameIndex = $index;
            }
        }

        return [$numberIndex, $nameIndex];
    }

    private static function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ',';
        }

        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');

        return $semicolons > $commas ? ';' : ',';
    }
}