<?php

namespace Tests\Support;

trait SnapshotHelper
{
    /**
     * Calcola lo SHA-256 reale di un file.
     */
    protected function calculateFileSha256(string $absolutePath): string
    {
        return hash_file('sha256', $absolutePath);
    }
    
    /**
     * Costruisce l'hash per un file mockato simulando il comportamento reale.
     */
    protected function calculateStringSha256(string $content): string
    {
        return hash('sha256', $content);
    }
}
