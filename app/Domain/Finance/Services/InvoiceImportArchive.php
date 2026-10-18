<?php

namespace App\Domain\Finance\Services;

use Illuminate\Validation\ValidationException;
use ZipArchive;

class InvoiceImportArchive
{
    public function files(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            $this->invalid('Il server non supporta gli archivi ZIP. Carica i singoli XML.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            $this->invalid('L’archivio ZIP non è leggibile.');
        }
        try {
            if ($zip->numFiles > 200) {
                $this->invalid('Lo ZIP contiene troppi elementi. Usa al massimo 100 documenti per importazione.');
            }
            $files = [];
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (! is_array($entry)) {
                    $this->invalid('Lo ZIP contiene un elemento non leggibile.');
                }
                $name = str_replace('\\', '/', $entry['name']);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(?:^|/)\.\.(?:/|$)|^[a-z]:~i', $name)) {
                    $this->invalid('Lo ZIP contiene percorsi non validi.');
                }
                $format = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($format, ['xml', 'p7m'], true)) {
                    $this->invalid('Lo ZIP deve contenere soltanto documenti XML o XML.P7M.');
                }
                $expanded += $entry['size'];
                if ($entry['size'] > 10 * 1024 * 1024 || $expanded > 20 * 1024 * 1024 || count($files) >= 100
                    || $entry['size'] > max(1, $entry['comp_size']) * 200 || ($entry['encryption_method'] ?? 0) !== 0) {
                    $this->invalid('Lo ZIP supera i limiti consentiti o contiene file protetti: massimo 100 documenti, 10 MB ciascuno e 20 MB complessivi estratti.');
                }
                /** Allocate only the declared size plus one byte; archive paths never become filesystem destinations. */
                $content = $zip->getFromIndex($index, $entry['size'] + 1);
                if (! is_string($content) || strlen($content) !== $entry['size']) {
                    $this->invalid('Non è stato possibile leggere integralmente un documento dello ZIP.');
                }
                $files[] = ['filename' => mb_substr(basename($name), 0, 255), 'format' => $format, 'content' => $content, 'source' => 'file'];
            }
            if ($files === []) {
                $this->invalid('Lo ZIP non contiene documenti XML.');
            }

            return $files;
        } finally {
            $zip->close();
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
