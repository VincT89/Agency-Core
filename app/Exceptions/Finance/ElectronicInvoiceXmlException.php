<?php

namespace App\Exceptions\Finance;

use RuntimeException;

class ElectronicInvoiceXmlException extends RuntimeException
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(implode(' ', $issues));
    }
}
