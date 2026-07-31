<?php

namespace App\Exceptions\Finance;

use RuntimeException;

class InvoiceFiscalPreparationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('La fattura non può essere preparata nello stato attuale.');
    }
}
