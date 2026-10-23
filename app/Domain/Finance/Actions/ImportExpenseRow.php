<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportExpenseRow
{
    public function execute(array $row, bool $apply = false): array
    {
        Gate::authorize('create', Expense::class);
        $data = $this->normalize($row['data']);
        $documentData = $row['document'] ?? null;
        $documentFingerprint = $documentData ? ExpenseDocument::fingerprintFor($documentData) : null;
        $core = collect($data)->only(['title', 'supplier', 'amount', 'expense_date', 'due_date', 'document_kind'])->all();
        $signature = hash('sha256', json_encode($core, JSON_THROW_ON_ERROR));
        $reference = trim((string) ($row['data']['reference'] ?? ''));
        $identity = $documentFingerprint ? ['document', $documentFingerprint] : ($reference !== ''
            ? ['reference', mb_strtoupper(trim($data['supplier'] ?? '')), $reference] : ['row', $core, $data['expense_document_id']]);
        $fingerprint = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        $storedPath = null;
        try {
            return DB::transaction(function () use ($row, $data, $apply, $documentData, $documentFingerprint, $signature, $reference, $fingerprint, &$storedPath) {
                $existing = Expense::where('import_fingerprint', $fingerprint)->lockForUpdate()->first();
                if ($existing) {
                    if (($existing->import_metadata['signature'] ?? null) !== $signature) $this->invalid('Lo stesso riferimento è già presente con dati diversi: verifica la spesa esistente.');
                    return ['status' => 'skipped', 'message' => 'Già presente: nessuna modifica', 'id' => $existing->id, 'data' => $data];
                }
                $document = $documentFingerprint ? ExpenseDocument::where('fingerprint', $documentFingerprint)->lockForUpdate()->first()
                    : ($data['expense_document_id'] ? ExpenseDocument::lockForUpdate()->find($data['expense_document_id']) : null);
                if ($documentFingerprint && $document && CashAmount::cents($document->amount) !== CashAmount::cents($documentData['amount'])) $this->invalid('Il documento esistente ha un importo diverso: verifica il file.');
                if ($documentFingerprint && $document?->expenses()->exists()) $this->invalid('Questa fattura fornitore ha già spese collegate: verifica gli importi prima di importare.');
                if (! $documentFingerprint && Expense::where('title', $data['title'])->where('supplier', $data['supplier'])
                    ->whereDate('expense_date', $data['expense_date'])->where('amount', $data['amount'])->whereNull('import_fingerprint')->exists()) {
                    $this->invalid('Esiste una spesa con titolo, fornitore, data e importo uguali: verifica il possibile duplicato.');
                }
                if ($data['status'] === 'paid' && ! $document) $this->invalid('Per una spesa pagata indica documento_id di una fattura o di un giustificativo già caricato.');
                if ($document && ($document->kind !== $data['document_kind'] || ! Storage::disk($document->disk)->exists($document->path))) $this->invalid('Il documento collegato non è disponibile oppure è di un tipo diverso.');
                if ($document && CashAmount::cents($document->expenses()->where('status', '!=', 'cancelled')->sum('amount')) + CashAmount::cents($data['amount']) > CashAmount::cents($document->amount)) $this->invalid('Le spese collegate supererebbero il totale del documento.');
                if (! $apply) return ['status' => 'ready', 'message' => $data['status'] === 'paid' ? 'Da importare come pagata' : 'Da importare come da pagare', 'data' => $data];
                if ($documentData) {
                    $document = app(StoreExpenseDocument::class)->execute($documentData, $row['content'], basename($row['filename']));
                    if ($document->wasRecentlyCreated) $storedPath = [$document->disk, $document->path];
                    $data['expense_document_id'] = $document->id;
                }
                $data['import_fingerprint'] = $fingerprint;
                $data['import_metadata'] = ['filename' => mb_substr($row['filename'], 0, 255), 'line' => $row['line'], 'reference' => $reference, 'signature' => $signature];
                $expense = app(SaveExpense::class)->execute($data);
                return ['status' => 'imported', 'message' => 'Spesa importata', 'id' => $expense->id, 'data' => $data];
            });
        } catch (\Throwable $exception) {
            /** Remove only a document file created by this failed transaction; existing attachments are preserved. */
            if ($storedPath) Storage::disk($storedPath[0])->delete($storedPath[1]);
            throw $exception;
        }
    }

    private function normalize(array $source): array
    {
        $source = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $source);
        $amount = $source['amount'] ?? '';
        if (is_float($amount)) {
            if (! is_finite($amount) || abs($amount - round($amount, 2)) > 0.0000001) $this->invalid('L’importo deve avere al massimo due decimali.');
            $amount = number_format($amount, 2, '.', '');
        }
        $amount = (string) $amount;
        if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d{1,2}$/', $amount)) $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '.', $amount);
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amount) || CashAmount::cents($amount) <= 0) $this->invalid('Importo non valido: usa un numero positivo con massimo due decimali.');
        $data = collect($source)->only(['title', 'description', 'supplier', 'notes', 'expense_document_id', 'document_kind'])->all();
        $data['amount'] = CashAmount::decimal(CashAmount::cents($amount));
        $data['supplier'] = ($data['supplier'] ?? '') ?: null;
        $data['expense_document_id'] = ($data['expense_document_id'] ?? '') ?: null;
        $data['document_kind'] = ($data['document_kind'] ?? '') ?: 'invoice';
        $data['status'] = match (mb_strtolower((string) ($source['status'] ?? ''))) {
            '', 'pending', 'da pagare' => 'pending', 'paid', 'pagata', 'pagato' => 'paid', default => 'invalid',
        };
        foreach (['expense_date', 'due_date', 'paid_at'] as $field) {
            $date = (string) ($source[$field] ?? '');
            if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $date, $parts)) $date = $parts[3].'-'.$parts[2].'-'.$parts[1];
            $data[$field] = $date ?: null;
        }
        Validator::make($data, ['title' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'supplier' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:5000', 'status' => 'required|in:pending,paid',
            'expense_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'paid_at' => 'nullable|required_if:status,paid|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'expense_document_id' => 'nullable|integer|exists:expense_documents,id', 'document_kind' => 'required|in:invoice,payslip,receipt,other'])->validate();
        if ($data['status'] === 'pending' && $data['paid_at']) $this->invalid('Una spesa da pagare non può avere una data di pagamento.');
        if (mb_strlen((string) ($source['reference'] ?? '')) > 100) $this->invalid('Il riferimento supera 100 caratteri.');
        $data['category_id'] = $this->category($source['category'] ?? null, null);
        if (filled($source['subcategory'] ?? null) && ! $data['category_id']) $this->invalid('Indica la categoria della sottocategoria.');
        $data['subcategory_id'] = $this->category($source['subcategory'] ?? null, $data['category_id']);
        return $data;
    }

    private function category(?string $name, ?int $parent): ?int
    {
        if (! filled($name)) return null;
        $category = ExpenseCategory::where('identity_key', ExpenseCategory::identity($name, $parent))->where('active', true)->first();
        if (! $category) $this->invalid('Categoria o sottocategoria non presente o non attiva: '.$name.'. Creala prima dell’importazione.');
        return $category->id;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['row' => $message]);
    }
}
