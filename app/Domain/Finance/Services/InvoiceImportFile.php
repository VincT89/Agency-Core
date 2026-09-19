<?php

namespace App\Domain\Finance\Services;

use Illuminate\Validation\ValidationException;

class InvoiceImportFile
{
    public function xml(string $content, string $extension): string
    {
        if ($extension === 'xml') {
            return $content;
        }
        if ($extension !== 'p7m' || ! function_exists('openssl_cms_verify') || strlen($content) > 10 * 1024 * 1024) {
            $this->invalid();
        }
        $input = tempnam(sys_get_temp_dir(), 'invoice-in-');
        $output = tempnam(sys_get_temp_dir(), 'invoice-out-');
        try {
            if (! $input || ! $output || file_put_contents($input, $content) === false) {
                $this->invalid();
            }
            // Extract the embedded document; no trust or fiscal-validity claim is made about its signature.
            $ok = @openssl_cms_verify($input, OPENSSL_CMS_NOVERIFY | OPENSSL_CMS_NOSIGS | OPENSSL_CMS_BINARY,
                null, [], null, $output, null, null, OPENSSL_ENCODING_DER);
            if (! $ok || filesize($output) > 10 * 1024 * 1024) {
                $this->invalid();
            }
            $xml = file_get_contents($output);
            if (! is_string($xml) || $xml === '') {
                $this->invalid();
            }

            return $xml;
        } finally {
            if ($input && is_file($input)) {
                unlink($input);
            }
            if ($output && is_file($output)) {
                unlink($output);
            }
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['document' => 'Impossibile leggere il file firmato. Usa l’XML non firmato oppure verifica che il server supporti OpenSSL CMS.']);
    }
}
