<?php

namespace App\Domain\Finance\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ZipArchive;

class ExpenseImportReader
{
    public const FORMATS = ['csv', 'xls', 'xlsx', 'xml'];
    public const MAX_ROWS = 500;

    public function read(string $path, string $filename): array
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) $this->invalid('Il file deve essere leggibile e non superare 10 MB.');
        $format = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($format, [...self::FORMATS, 'zip'], true)) $this->invalid('Carica un file XLS, XLSX, CSV, XML oppure ZIP.');
        if ($format !== 'zip') return $this->single($path, $filename, $format);
        $rows = [];
        $zip = $this->archive($path, false);
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (str_ends_with($entry['name'], '/')) continue;
                $content = $zip->getFromIndex($i, $entry['size'] + 1);
                if (! is_string($content) || strlen($content) !== $entry['size']) $this->invalid('Un file dello ZIP non è leggibile.');
                $temporary = tempnam(sys_get_temp_dir(), 'expense-import-');
                if (! $temporary) $this->invalid('Impossibile preparare la lettura del file.');
                try {
                    if (file_put_contents($temporary, $content) !== strlen($content)) $this->invalid('Impossibile leggere integralmente il file.');
                    array_push($rows, ...$this->single($temporary, basename($entry['name']), strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION))));
                    if (count($rows) > self::MAX_ROWS) $this->invalid('Importa al massimo 500 righe per volta.');
                } finally {
                    unlink($temporary);
                }
            }
        } finally {
            $zip->close();
        }
        if (! $rows) $this->invalid('Lo ZIP non contiene righe importabili.');
        return $rows;
    }

    private function single(string $path, string $filename, string $format): array
    {
        if ($format === 'xml') {
            $content = file_get_contents($path);
            $documents = app(InvoiceImportReader::class)->read($content, 'in');
            return array_map(function ($document) use ($content, $filename) {
                $party = $document['counterparty'];
                $source = ['kind' => 'invoice', 'issuer' => $party['name'], 'issuer_identifier' => $party['vat_number'] ?: $party['tax_code'],
                    'issuer_country' => $party['country_code'], 'number' => $document['number'], 'document_date' => $document['issue_date'],
                    'due_date' => $document['due_date'], 'amount' => $document['total'], 'currency' => 'EUR'];
                return ['filename' => $filename, 'line' => $document['body_index'] + 1, 'document' => $source, 'content' => $content,
                    'data' => ['title' => 'Fattura '.$document['number'], 'supplier' => $party['name'], 'amount' => $document['payable_total'],
                        'expense_date' => $document['issue_date'], 'due_date' => $document['due_date'], 'status' => 'pending', 'document_kind' => 'invoice']];
            }, $documents);
        }
        if ($format === 'csv') {
            $handle = fopen($path, 'rb');
            try {
                $first = fgets($handle, 65537);
                if (! is_string($first)) $this->invalid('Il CSV è vuoto.');
                $separator = ';';
                foreach ([',', "\t"] as $candidate) {
                    if (count(str_getcsv($first, $candidate, '"', '')) > count(str_getcsv($first, $separator, '"', ''))) $separator = $candidate;
                }
                rewind($handle);
                $table = [];
                while (($line = fgetcsv($handle, 65537, $separator, '"', '')) !== false) {
                    if (count($line) > 30 || count($table) > self::MAX_ROWS) $this->invalid('Il CSV supera 500 righe o 30 colonne.');
                    foreach ($line as $value) {
                        if ($value !== null && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || strlen($value) > 10000)) $this->invalid('Usa un CSV UTF-8 con celle di massimo 10.000 caratteri.');
                    }
                    $table[] = $line;
                }
                return $this->table($table, $filename);
            } finally {
                fclose($handle);
            }
        }
        if ($format === 'xlsx') $this->archive($path, true)->close();
        try {
            $reader = IOFactory::createReader($format === 'xls' ? 'Xls' : 'Xlsx');
            if (! $reader->canRead($path)) $this->invalid('Il contenuto non corrisponde al formato Excel indicato.');
            $info = $reader->listWorksheetInfo($path);
            if (count($info) > 10 || array_sum(array_column($info, 'totalRows')) > self::MAX_ROWS + count($info)
                || max(array_column($info, 'totalColumns') ?: [0]) > 30) $this->invalid('Il file Excel supera 500 righe, 30 colonne o 10 fogli.');
            $reader->setReadDataOnly(true)->setIncludeCharts(false);
            $book = $reader->load($path);
            $calendar = Date::getExcelCalendar();
            try {
                Date::setExcelCalendar($book->getExcelCalendar());
                $rows = [];
                foreach ($book->getWorksheetIterator() as $sheet) {
                    $table = [];
                    foreach ($sheet->getRowIterator() as $row) {
                        $values = [];
                        foreach ($row->getCellIterator() as $cell) {
                            /** Never evaluate formulas or external links supplied by an uploaded workbook. */
                            if ($cell->getDataType() === DataType::TYPE_FORMULA) $this->invalid('Sostituisci le formule Excel con i valori prima dell’importazione.');
                            $value = $cell->getValue();
                            if (is_string($value) && strlen($value) > 10000) $this->invalid('Una cella supera 10.000 caratteri.');
                            $values[] = $value;
                        }
                        $table[] = $values;
                    }
                    if (count($table) === 1 && ! array_filter($table[0], fn ($value) => $value !== null && $value !== '')) continue;
                    array_push($rows, ...$this->table($table, $filename.' / '.$sheet->getTitle(), true));
                }
                if (count($rows) > self::MAX_ROWS) $this->invalid('Importa al massimo 500 righe per volta.');
                return $rows;
            } finally {
                Date::setExcelCalendar($calendar);
                $book->disconnectWorksheets();
            }
        } catch (\PhpOffice\PhpSpreadsheet\Exception $exception) {
            $this->invalid('Il file Excel non è leggibile o contiene elementi non supportati.');
        }
    }

    private function table(array $table, string $filename, bool $excel = false): array
    {
        $aliases = ['titolo' => 'title', 'descrizione' => 'description', 'importo' => 'amount', 'data' => 'expense_date', 'data_spesa' => 'expense_date',
            'scadenza' => 'due_date', 'data_pagamento' => 'paid_at', 'fornitore' => 'supplier', 'stato' => 'status', 'categoria' => 'category',
            'sottocategoria' => 'subcategory', 'note' => 'notes', 'riferimento' => 'reference', 'documento_id' => 'expense_document_id', 'tipo_documento' => 'document_kind'];
        $headers = array_map(function ($value) use ($aliases) {
            $key = str_replace(' ', '_', mb_strtolower(trim(ltrim((string) $value, "\xEF\xBB\xBF"))));
            return $aliases[$key] ?? $key;
        }, array_shift($table) ?? []);
        if (array_diff(['title', 'amount', 'expense_date'], $headers) || count(array_unique($headers)) !== count($headers)
            || array_diff($headers, array_values($aliases))) $this->invalid('Intestazioni non valide: servono titolo, importo e data_spesa. Usa soltanto le colonne indicate nella pagina di importazione.');
        $rows = [];
        foreach ($table as $index => $values) {
            if (! array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) continue;
            if (count($values) > count($headers)) $this->invalid('La riga '.($index + 2).' contiene più colonne dell’intestazione.');
            $data = array_combine($headers, array_pad($values, count($headers), null));
            if ($excel) {
                foreach (['expense_date', 'due_date', 'paid_at'] as $key) {
                    if (isset($data[$key]) && is_numeric($data[$key]) && (float) $data[$key] >= 1 && (float) $data[$key] <= 73415) {
                        $data[$key] = Date::excelToDateTimeObject((float) $data[$key])->format('Y-m-d');
                    }
                }
            }
            $rows[] = ['filename' => $filename, 'line' => $index + 2, 'data' => $data];
        }
        return $rows;
    }

    private function archive(string $path, bool $workbook): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) $this->invalid('Archivio ZIP non leggibile.');
        try {
            if ($zip->numFiles > ($workbook ? 1000 : 30)) $this->invalid('L’archivio contiene troppi elementi.');
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (! $entry) $this->invalid('L’archivio contiene un elemento non leggibile.');
                $name = str_replace('\\', '/', $entry['name']);
                if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(?:^|/)\.\.(?:/|$)|^[a-z]:~i', $name)) $this->invalid('L’archivio contiene percorsi non validi.');
                if (str_ends_with($name, '/')) continue;
                $expanded += $entry['size'];
                if ($entry['size'] > 10 * 1024 * 1024 || $expanded > 20 * 1024 * 1024
                    || $entry['size'] > max(1, $entry['comp_size']) * 200 || ($entry['encryption_method'] ?? 0) !== 0) $this->invalid('Archivio troppo grande, troppo compresso o protetto: massimo 20 MB dopo la decompressione.');
                if (! $workbook && ! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::FORMATS, true)) $this->invalid('Lo ZIP può contenere soltanto XLS, XLSX, CSV e XML, senza altri archivi.');
            }
            return $zip;
        } catch (\Throwable $exception) {
            $zip->close();
            throw $exception;
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
