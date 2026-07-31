<?php

namespace App\Domain\Finance\DTOs;

final readonly class InvoiceFiscalReadiness
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(public array $issues) {}

    public function isReady(): bool
    {
        return $this->issues === [];
    }
}
