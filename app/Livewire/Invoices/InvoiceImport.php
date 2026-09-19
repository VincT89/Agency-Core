<?php

namespace App\Livewire\Invoices;

use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Actions\StoreExpenseDocument;
use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\InvoiceImportFile;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Exceptions\Finance\ArubaApiException;
use App\Exceptions\Finance\ArubaConfigurationException;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoiceImport extends Component
{
    use WithFileUploads;

    public string $direction = 'out';

    public string $source = 'file';

    public $file;

    public string $from = '';

    public string $to = '';

    public int $bodyIndex = 0;

    public $clientId = null;

    public bool $createClient = false;

    public string $dueDate = '';

    public bool $confirmed = false;

    public array $manual = ['number' => '', 'issue_date' => '', 'subtotal' => '', 'tax_amount' => '', 'total' => '', 'issuer' => '', 'identifier' => '', 'country' => 'IT'];

    #[Locked]
    public ?string $sourceToken = null;

    #[Locked]
    public array $documents = [];

    #[Locked]
    public bool $pdfForm = false;

    #[Locked]
    public array $arubaResults = [];

    #[Locked]
    public int $arubaPage = 1;

    #[Locked]
    public bool $arubaLast = true;

    #[Locked]
    public bool $searched = false;

    #[Locked]
    public string $sourceLabel = '';

    #[Locked]
    public string $sourceFormat = '';

    public function mount(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->from = $this->to = today()->toDateString();
    }

    public function updatedDirection(): void
    {
        $this->clearPreview();
        $this->reset('arubaResults', 'searched');
    }

    public function updatedSource(): void
    {
        $this->clearPreview();
        $this->reset('arubaResults', 'searched');
    }

    public function updatedFile(): void
    {
        $this->clearPreview();
    }

    public function clearPreview(): void
    {
        Gate::authorize('create', Invoice::class);
        if ($this->sourceToken) {
            Cache::forget($this->cacheKey());
        }
        $this->reset('sourceToken', 'documents', 'pdfForm', 'bodyIndex', 'clientId', 'createClient', 'confirmed', 'dueDate', 'sourceLabel', 'sourceFormat');
        $this->resetValidation();
    }

    public function previewFile(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['direction' => 'required|in:in,out', 'source' => 'required|in:file', 'file' => 'required|file|max:10240|extensions:xml,pdf,p7m']);
        $this->clearPreview();
        $this->prepare($this->file->get(), $this->file->getClientOriginalName(), ['source' => 'file']);
    }

    public function searchAruba(int $page = 1): void
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['direction' => 'required|in:in,out', 'source' => 'required|in:aruba', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        if ($page < 1 || $page > 10000) {
            abort(422);
        }
        $from = CarbonImmutable::parse($this->from)->startOfDay();
        $to = CarbonImmutable::parse($this->to)->endOfDay();
        if ($from->diffInSeconds($to) > 172800) {
            throw ValidationException::withMessages(['to' => 'Seleziona al massimo due giorni consecutivi di acquisizione su Aruba.']);
        }
        $profile = $this->profile();
        $this->arubaRateLimit();
        try {
            $client = app(ArubaInvoiceClient::class);
            $result = $this->direction === 'in'
                ? $client->receivedInvoices($from->toIso8601String(), $to->toIso8601String(), $page, $profile->vat_country_code, $profile->vat_number)
                : $client->issuedInvoices($from->toIso8601String(), $to->toIso8601String(), $page, $profile->vat_country_code, $profile->vat_number);
        } catch (ArubaApiException|ArubaConfigurationException $exception) {
            throw ValidationException::withMessages(['aruba' => $exception->getMessage()]);
        }
        if (! is_array($result['content'] ?? null)) {
            throw ValidationException::withMessages(['aruba' => 'Aruba non ha restituito un elenco leggibile.']);
        }
        $this->arubaResults = [];
        foreach ($result['content'] as $entry) {
            if (! is_array($entry) || ($entry['docType'] ?? null) !== $this->direction || ! is_scalar($entry['id'] ?? null)) {
                continue;
            }
            foreach (($entry['invoices'] ?? []) as $index => $invoice) {
                if (! is_array($invoice)) {
                    continue;
                }
                $this->arubaResults[] = ['id' => (string) $entry['id'], 'body_index' => (int) $index,
                    'name' => (string) ($entry[$this->direction === 'in' ? 'sender' : 'receiver']['description'] ?? 'Anagrafica da verificare'),
                    'number' => (string) ($invoice['number'] ?? ''), 'date' => (string) ($invoice['invoiceDate'] ?? ''),
                    'total' => is_numeric($invoice['totalDocument'] ?? null) ? (string) $invoice['totalDocument'] : '',
                    'status' => (string) ($invoice['status'] ?? '')];
            }
        }
        $this->arubaPage = $page;
        $this->arubaLast = (bool) ($result['last'] ?? true);
        $this->searched = true;
    }

    public function previewAruba(int $row): void
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['source' => 'required|in:aruba', 'direction' => 'required|in:in,out']);
        $entry = $this->arubaResults[$row] ?? null;
        if (! $entry) {
            abort(404);
        }
        $this->clearPreview();
        $payload = $this->arubaDetail($this->direction, $entry['id']);
        $encoded = ($payload['unsignedFile'] ?? '') ?: ($payload['file'] ?? '');
        $content = is_string($encoded) && strlen($encoded) <= 15 * 1024 * 1024 ? base64_decode($encoded, true) : false;
        if (! $content || strlen($content) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['aruba' => 'Il file restituito da Aruba non è valido o supera 10 MB.']);
        }
        $filename = (string) ($payload['filename'] ?? 'fattura.xml');
        if (filled($payload['unsignedFile'] ?? null)) {
            $filename = preg_replace('/\.p7m$/i', '', $filename);
        }
        $configuration = app(ArubaConfiguration::class);
        $profile = $this->profile();
        $this->prepare($content, $filename, ['source' => 'aruba', 'aruba_id' => $entry['id'], 'environment' => $configuration->environment(),
            'aruba_account' => hash('sha256', implode('|', [$configuration->environment(), $configuration->username(), $profile->vat_country_code, $profile->vat_number])),
            'aruba_statuses' => array_map(fn ($invoice) => $invoice['status'] ?? '', $payload['invoices'] ?? []),
        ]);
        $this->bodyIndex = $entry['body_index'];
        $this->updatedBodyIndex();
    }

    public function updatedBodyIndex(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->reset('confirmed', 'clientId', 'createClient');
        $document = $this->documents[$this->bodyIndex] ?? null;
        $this->dueDate = $document['due_date'] ?? '';
        if ($document && $this->direction === 'out') {
            $importer = app(ImportIssuedInvoice::class);
            $matches = $importer->matchingClients($document['counterparty'])->filter(fn ($client) => $importer->matches($client, $document['counterparty']));
            if ($matches->count() === 1) {
                $this->clientId = $matches->first()->id;
            }
        }
    }

    public function reviewPdf(): void
    {
        Gate::authorize('create', Invoice::class);
        $source = $this->preparedSource();
        if ($source['format'] !== 'pdf') {
            abort(422);
        }
        $this->validate(['manual.number' => 'required|string|max:100', 'manual.issue_date' => 'required|date_format:Y-m-d',
            'manual.total' => 'required|numeric|decimal:0,2|between:0.01,99999999.99',
            'manual.subtotal' => $this->direction === 'out' ? 'required|numeric|decimal:0,2|between:0,99999999.99' : 'nullable',
            'manual.tax_amount' => $this->direction === 'out' ? 'required|numeric|decimal:0,2|between:0,99999999.99' : 'nullable',
            'manual.issuer' => $this->direction === 'in' ? 'required|string|max:255' : 'nullable',
            'manual.identifier' => $this->direction === 'in' ? 'required|string|max:50' : 'nullable',
            'manual.country' => 'required|alpha|size:2',
            'clientId' => $this->direction === 'out' ? 'required|integer|exists:clients,id' : 'nullable']);
        if ($this->direction === 'out' && CashAmount::cents($this->manual['subtotal']) + CashAmount::cents($this->manual['tax_amount']) !== CashAmount::cents($this->manual['total'])) {
            throw ValidationException::withMessages(['manual.total' => 'Il totale deve corrispondere a imponibile più IVA. Per documenti con rettifiche usa l’XML originale.']);
        }
        $profile = $this->profile();
        $client = $this->direction === 'out' ? Client::findOrFail($this->clientId) : null;
        $party = $client ? $client->only(['name', 'vat_number', 'tax_code', 'address', 'city', 'postal_code', 'province', 'country_code'])
            : ['name' => $this->manual['issuer'], 'vat_number' => $this->manual['identifier'], 'tax_code' => '', 'country_code' => strtoupper($this->manual['country'])];
        $documents = [[
            'body_index' => 0, 'direction' => $this->direction, 'document_type' => 'TD01', 'number' => $this->manual['number'],
            'issue_date' => $this->manual['issue_date'], 'due_date' => null, 'currency' => 'EUR',
            'subtotal' => $client ? CashAmount::decimal(CashAmount::cents($this->manual['subtotal'])) : null,
            'tax_amount' => $client ? CashAmount::decimal(CashAmount::cents($this->manual['tax_amount'])) : null,
            'total' => CashAmount::decimal(CashAmount::cents($this->manual['total'])), 'counterparty' => $party,
            'agency' => ['vat_number' => $profile->vat_number, 'country_code' => $profile->vat_country_code], 'items' => [], 'payments' => [], 'client_id' => $client?->id,
        ]];
        $source['documents'] = $documents;
        $this->storeSource($source);
        $this->documents = $documents;
        $this->pdfForm = false;
        $this->confirmed = false;
    }

    public function editPdf(): void
    {
        Gate::authorize('create', Invoice::class);
        $source = $this->preparedSource();
        if ($source['format'] !== 'pdf') {
            abort(422);
        }
        $source['documents'] = [];
        $this->storeSource($source);
        $this->documents = [];
        $this->pdfForm = true;
        $this->confirmed = false;
    }

    public function import()
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['confirmed' => 'accepted', 'bodyIndex' => 'integer|min:0|max:99', 'dueDate' => 'nullable|date_format:Y-m-d',
            'clientId' => $this->direction === 'out' && ! $this->createClient ? 'required|integer|exists:clients,id' : 'nullable|integer', 'createClient' => 'boolean']);
        $source = $this->preparedSource();
        $document = $source['documents'][$this->bodyIndex] ?? null;
        if (! $document) {
            throw ValidationException::withMessages(['document' => 'Seleziona e verifica una fattura prima di importarla.']);
        }
        app(InvoiceImportReader::class)->assertAgency($document['agency']);
        if ($this->direction === 'out' && $source['source'] === 'aruba') {
            $payload = $this->arubaDetail('out', $source['aruba_id']);
            $status = mb_strtolower((string) ($payload['invoices'][$this->bodyIndex]['status'] ?? ''));
            if (! in_array($status, ['inviata', 'non consegnata', 'recapito impossibile', 'consegnata', 'accettata', 'decorrenza termini'], true)) {
                throw ValidationException::withMessages(['document' => 'La fattura risulta «'.($status ?: 'stato non disponibile').'» su Aruba. Verifica l’esito prima di registrarla come fattura emessa.']);
            }
            $encoded = ($payload['unsignedFile'] ?? '') ?: ($payload['file'] ?? '');
            if (! is_string($encoded) || ! hash_equals(hash('sha256', $source['content']), hash('sha256', base64_decode($encoded, true) ?: ''))) {
                throw ValidationException::withMessages(['document' => 'Il documento Aruba è cambiato. Riapri l’anteprima prima di importarlo.']);
            }
        }
        if ($this->direction === 'out') {
            if ($source['format'] === 'pdf' && $this->createClient) {
                abort(422);
            }
            $invoice = app(ImportIssuedInvoice::class)->execute($document, $source, $this->clientId ? (int) $this->clientId : null, $this->createClient, $this->dueDate ?: null);
            session()->flash('success', $invoice->wasRecentlyCreated ? 'Fattura importata. Gli incassi vanno registrati separatamente.' : 'Fattura già presente: è stata aperta senza duplicarla.');
            $route = route('invoices.show', $invoice);
        } else {
            Gate::authorize('create', Expense::class);
            $party = $document['counterparty'];
            $data = ['kind' => 'invoice', 'issuer' => $party['name'], 'issuer_identifier' => ($party['vat_number'] ?? '') ?: ($party['tax_code'] ?? ''),
                'issuer_country' => $party['country_code'], 'number' => $document['number'], 'document_date' => $document['issue_date'],
                'due_date' => $this->dueDate ?: null, 'amount' => $document['total'], 'currency' => 'EUR'];
            Validator::make($data, ['issuer_identifier' => 'required|string|max:50'])->validate();
            if ($source['source'] === 'aruba') {
                $data += ['aruba_environment' => $source['environment'], 'aruba_account' => $source['aruba_account'], 'aruba_id' => $source['aruba_id'], 'aruba_body_index' => $this->bodyIndex];
            }
            $expenseDocument = app(StoreExpenseDocument::class)->execute($data, $source['content'], $source['filename']);
            session()->flash('success', 'Fattura ricevuta acquisita. Collegala a una spesa esistente oppure crea una nuova uscita.');
            $route = route('expenses.documents.show', $expenseDocument);
        }
        Cache::forget($this->cacheKey());

        return $this->redirect($route);
    }

    private function prepare(string $content, string $filename, array $source): void
    {
        $filename = mb_substr(basename(str_replace('\\', '/', $filename)), 0, 255);
        $format = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($format, ['xml', 'pdf', 'p7m'], true)) {
            throw ValidationException::withMessages(['document' => 'Scegli un file XML, XML.P7M o PDF.']);
        }
        $this->profile();
        if ($format === 'pdf' && ! str_starts_with($content, '%PDF-')) {
            throw ValidationException::withMessages(['document' => 'Il file non è un PDF leggibile.']);
        }
        $documents = $format === 'pdf' ? [] : app(InvoiceImportReader::class)->read(app(InvoiceImportFile::class)->xml($content, $format), $this->direction);
        $this->sourceToken = (string) Str::uuid();
        $this->storeSource($source + ['direction' => $this->direction, 'content' => $content, 'filename' => $filename, 'format' => $format, 'documents' => $documents]);
        $this->documents = $documents;
        $this->pdfForm = $format === 'pdf';
        $this->sourceFormat = $format;
        $this->sourceLabel = $filename.($source['source'] === 'aruba' ? ' · Aruba '.($source['environment'] === 'production' ? 'produzione' : 'demo') : '');
        $this->updatedBodyIndex();
    }

    private function preparedSource(): array
    {
        $source = $this->sourceToken ? Cache::get($this->cacheKey()) : null;
        if (! is_array($source) || $source['direction'] !== $this->direction || $source['source'] !== $this->source) {
            throw ValidationException::withMessages(['document' => 'L’anteprima è scaduta o non corrisponde alla selezione. Carica nuovamente il documento.']);
        }
        $decoded = ($source['encoding'] ?? '') === 'base64' ? base64_decode($source['content'], true) : false;
        if ($decoded === false) {
            throw ValidationException::withMessages(['document' => 'Carica nuovamente il documento per aggiornare l’anteprima.']);
        }
        $source['content'] = $decoded;

        return $source;
    }

    private function storeSource(array $source): void
    {
        // Database caches use text columns: binary PDF/P7M bytes must not be stored as raw UTF-8 text.
        $source['content'] = base64_encode($source['content']);
        $source['encoding'] = 'base64';
        if (strlen(serialize($source)) > 15 * 1024 * 1024) {
            throw ValidationException::withMessages(['document' => 'Il lotto è troppo complesso per una singola anteprima. Importa le fatture come file separati.']);
        }
        if (! Cache::put($this->cacheKey(), $source, now()->addMinutes(20))) {
            throw ValidationException::withMessages(['document' => 'Impossibile conservare l’anteprima. Riprova il caricamento.']);
        }
    }

    private function cacheKey(): string
    {
        return 'invoice-import:'.auth()->id().':'.$this->sourceToken;
    }

    private function profile(): BillingProfile
    {
        $profile = BillingProfile::current();
        if (! $profile?->vat_number || ! $profile->vat_country_code) {
            throw ValidationException::withMessages(['document' => 'Completa prima la partita IVA e il paese nei dati fiscali dell’agenzia.']);
        }

        return $profile;
    }

    private function arubaRateLimit(): void
    {
        $key = 'invoice-import-aruba:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages(['aruba' => 'Attendi un minuto prima di interrogare nuovamente Aruba.']);
        }
        RateLimiter::hit($key, 60);
    }

    private function arubaDetail(string $direction, string $id): array
    {
        $this->arubaRateLimit();
        try {
            $client = app(ArubaInvoiceClient::class);
            $payload = $direction === 'in' ? $client->receivedInvoiceDetail($id) : $client->issuedInvoiceFile($id);
        } catch (ArubaApiException|ArubaConfigurationException $exception) {
            throw ValidationException::withMessages(['aruba' => $exception->getMessage()]);
        }
        if (($payload['docType'] ?? null) !== $direction || (string) ($payload['id'] ?? '') !== $id) {
            throw ValidationException::withMessages(['aruba' => 'Aruba non ha restituito la fattura richiesta.']);
        }

        return $payload;
    }

    public function render()
    {
        Gate::authorize('create', Invoice::class);

        return view('livewire.invoices.invoice-import', ['selected' => $this->documents[$this->bodyIndex] ?? null,
            'selectedClient' => $this->clientId && is_scalar($this->clientId) ? Client::find($this->clientId) : null])->layout('layouts.app', ['title' => 'Importa fattura']);
    }
}
