<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class MemberSpreadsheetParser
{
    /** @return list<array{name: string, member_code: string, birth_date: ?string, phone: ?string, has_jersey: bool}> */
    public function parse(string $path, string $extension): array
    {
        return strtolower($extension) === 'xlsx'
            ? $this->parseXlsx($path)
            : $this->parseCsv($path);
    }

    /** @return list<array{name: string, member_code: string, birth_date: ?string, phone: ?string, has_jersey: bool}> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File tidak dapat dibaca.');
        }

        try {
            $firstLine = (string) fgets($handle);
            rewind($handle);
            $delimiter = $this->detectDelimiter($firstLine);
            $rows = [];

            while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $this->mapRows($rows);
    }

    /** @return list<array{name: string, member_code: string, birth_date: ?string, phone: ?string, has_jersey: bool}> */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException('Lembar kerja pertama tidak dapat dibaca.');
            }

            $worksheet = $this->loadXml($sheetXml);
            $worksheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];

            foreach ($worksheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
                $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cells = [];

                foreach ($row->xpath('./m:c') ?: [] as $cell) {
                    $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $attributes = $cell->attributes();
                    $reference = (string) ($attributes['r'] ?? '');

                    if (preg_match('/^([A-Z]+)/i', $reference, $matches) !== 1) {
                        continue;
                    }

                    $column = $this->columnIndex(strtoupper($matches[1]));
                    $type = (string) ($attributes['t'] ?? '');
                    $valueNodes = $cell->xpath('./m:v');
                    $value = (string) ($valueNodes[0] ?? '');

                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $textNodes = $cell->xpath('.//m:is//m:t');
                        $value = implode('', array_map(static fn (SimpleXMLElement $node): string => (string) $node, $textNodes ?: []));
                    }

                    $cells[$column] = $value;
                }

                if ($cells !== []) {
                    ksort($cells);
                    $denseCells = array_fill(0, max(array_keys($cells)) + 1, '');

                    foreach ($cells as $column => $value) {
                        $denseCells[$column] = $value;
                    }

                    $rows[] = $denseCells;
                }
            }
        } finally {
            $zip->close();
        }

        return $this->mapRows($rows);
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = $this->loadXml($xml);
        $strings->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];

        foreach ($strings->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $textNodes = $item->xpath('.//m:t');
            $values[] = implode('', array_map(static fn (SimpleXMLElement $node): string => (string) $node, $textNodes ?: []));
        }

        return $values;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationshipsXml === false) {
            throw new RuntimeException('Struktur file Excel tidak valid.');
        }

        $workbook = $this->loadXml($workbookXml);
        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $firstSheet = $workbook->xpath('//m:sheets/m:sheet')[0] ?? null;
        $relationshipId = $firstSheet ? (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        if ($relationshipId === '') {
            throw new RuntimeException('File Excel tidak memiliki lembar kerja.');
        }

        $relationships = $this->loadXml($relationshipsXml);
        $relationships->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $target = null;

        foreach ($relationships->xpath('//p:Relationship') ?: [] as $relationship) {
            $attributes = $relationship->attributes();

            if ((string) ($attributes['Id'] ?? '') === $relationshipId) {
                $target = (string) ($attributes['Target'] ?? '');
                break;
            }
        }

        if (! $target || str_contains($target, '..')) {
            throw new RuntimeException('Jalur lembar kerja pada file Excel tidak valid.');
        }

        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
    }

    /** @param list<array<int, string|null>> $rows
     * @return list<array{name: string, member_code: string, birth_date: ?string, phone: ?string, has_jersey: bool}>
     */
    private function mapRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('File tidak memiliki baris header.');
        }

        $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $rows[0]);
        $columns = [];

        foreach ($headers as $index => $header) {
            $field = match ($header) {
                'nama', 'namaanggota', 'name', 'membername' => 'name',
                'kode', 'kodeanggota', 'membercode', 'code' => 'member_code',
                'tanggallahir', 'birthdate', 'dateofbirth' => 'birth_date',
                'nohp', 'notelpon', 'notelepon', 'nomortelepon', 'nomorhp', 'nomorponsel', 'phone', 'telp', 'telepon' => 'phone',
                'memilikijersey', 'statusjersey', 'jersey' => 'has_jersey',
                default => null,
            };

            if ($field !== null) {
                $columns[$field] = $index;
            }
        }

        if (! array_key_exists('name', $columns)) {
            throw new RuntimeException('Header Nama Anggota tidak ditemukan. Gunakan kolom Nama Anggota, Kode Anggota, Tanggal Lahir, No Telepon, dan Memiliki Jersey.');
        }

        $members = [];

        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row[$columns['name']] ?? ''));
            $memberCode = isset($columns['member_code']) ? trim((string) ($row[$columns['member_code']] ?? '')) : '';
            $birthDate = isset($columns['birth_date']) ? trim((string) ($row[$columns['birth_date']] ?? '')) : '';
            $phone = isset($columns['phone']) ? trim((string) ($row[$columns['phone']] ?? '')) : '';
            $jerseyValue = isset($columns['has_jersey']) ? mb_strtolower(trim((string) ($row[$columns['has_jersey']] ?? ''))) : '';

            if ($name === '' && $memberCode === '' && $birthDate === '' && $phone === '' && $jerseyValue === '') {
                continue;
            }

            $hasJersey = match ($jerseyValue) {
                'sudah punya', 'sudah', 'punya', 'ya', 'yes', '1', 'true' => true,
                'belum punya', 'belum', 'tidak', 'no', '0', 'false' => false,
                default => null,
            };

            $members[] = [
                'name' => $name,
                'member_code' => $memberCode,
                'birth_date' => $this->normalizeDate($birthDate),
                'phone' => $phone !== '' ? $phone : null,
                'has_jersey' => $hasJersey,
            ];
        }

        return $members;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(["\xEF\xBB\xBF", ' ', '_', '-', '.'], '', $header);

        return preg_replace('/[^a-z0-9]/', '', $header) ?? '';
    }

    private function normalizeDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{5}(?:\.\d+)?$/', $value) === 1) {
            $serial = (int) $value;

            if ($serial >= 15000 && $serial <= 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify("+{$serial} days")->format('Y-m-d');
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function detectDelimiter(string $header): string
    {
        $delimiters = [',', ';', "\t"];

        usort($delimiters, static fn (string $left, string $right): int => substr_count($header, $right) <=> substr_count($header, $left));

        return $delimiters[0];
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function loadXml(string $xml): SimpleXMLElement
    {
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($document === false) {
            throw new RuntimeException('Isi file Excel tidak valid.');
        }

        return $document;
    }
}
