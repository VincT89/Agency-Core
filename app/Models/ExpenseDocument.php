<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseDocument extends Model
{
    public const KINDS = ['invoice' => 'Fattura fornitore', 'payslip' => 'Cedolino', 'receipt' => 'Ricevuta', 'other' => 'Altro giustificativo'];

    protected $fillable = ['user_id', 'kind', 'issuer', 'issuer_identifier', 'issuer_country', 'number', 'document_date', 'due_date', 'amount', 'currency', 'disk', 'path', 'original_name', 'fingerprint', 'aruba_environment', 'aruba_account', 'aruba_id', 'aruba_body_index'];

    protected $casts = ['document_date' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2'];

    protected $hidden = ['path', 'disk', 'aruba_account'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function fingerprintFor(array $data): string
    {
        $normalize = fn ($value) => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)));

        return hash('sha256', implode('|', [
            $data['kind'], $normalize($data['issuer_country'] ?? ($data['kind'] === 'invoice' ? 'IT' : '')),
            $normalize(($data['issuer_identifier'] ?? '') ?: $data['issuer']),
            $normalize($data['number']), substr($data['document_date'], 0, 10), $data['currency'] ?? 'EUR',
        ]));
    }
}
