<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesInvoiceLines
{
    protected function addInvoiceLineTaxErrors(Validator $validator): void
    {
        foreach ((array) $this->input('items', []) as $index => $line) {
            if (! array_key_exists('vat_rate', $line) || $line['vat_rate'] === '' || $line['vat_rate'] === null) {
                continue;
            }

            $vatRate = (float) $line['vat_rate'];
            $nature = $line['vat_nature'] ?? null;
            $reference = $line['vat_reference'] ?? null;
            $position = $index + 1;

            if ($vatRate === 0.0) {
                if (blank($nature)) {
                    $validator->errors()->add(
                        "items.{$index}.vat_nature",
                        "Seleziona la natura IVA per la voce {$position}."
                    );
                }

                if (blank($reference)) {
                    $validator->errors()->add(
                        "items.{$index}.vat_reference",
                        "Indica il riferimento normativo per la voce {$position}."
                    );
                }
            } elseif (filled($nature)) {
                $validator->errors()->add(
                    "items.{$index}.vat_nature",
                    "La natura IVA va indicata solo con aliquota zero nella voce {$position}."
                );
            }
        }
    }
}
