<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Models\ExpenseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreExpenseDocument
{
    public function execute(array $data, string $content, string $filename): ExpenseDocument
    {
        $data += ['issuer_country' => $data['kind'] === 'invoice' ? 'IT' : null];
        $data['fingerprint'] = ExpenseDocument::fingerprintFor($data);
        $path = 'expense-documents/'.Str::uuid().'.'.strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $data += ['currency' => 'EUR', 'user_id' => auth()->id()];
        $data['disk'] = 'attachments';
        $data['path'] = $path;
        $data['original_name'] = mb_substr(basename(str_replace('\\', '/', $filename)), 0, 255);
        if (! Storage::disk('attachments')->put($path, $content)) {
            throw new RuntimeException('Impossibile salvare il documento.');
        }
        try {
            $document = DB::transaction(function () use ($data) {
                $document = ExpenseDocument::lockForUpdate()->firstOrCreate(['fingerprint' => $data['fingerprint']], $data);
                if (CashAmount::cents($document->amount) !== CashAmount::cents($data['amount'])) {
                    throw ValidationException::withMessages(['document' => 'Esiste già un documento con questi riferimenti ma con importo diverso. Verificalo prima di proseguire.']);
                }
                if (! Storage::disk($document->disk)->exists($document->path)) {
                    $document->update(collect($data)->only(['disk', 'path', 'original_name'])->all());
                }
                if (! $document->wasRecentlyCreated && isset($data['aruba_id'])) {
                    $document->update(collect($data)->only(['aruba_id', 'aruba_environment', 'aruba_account', 'aruba_body_index'])->all());
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('attachments')->delete($path);
            throw $exception;
        }
        if ($document->path !== $path) {
            Storage::disk('attachments')->delete($path);
        }

        return $document;
    }
}
