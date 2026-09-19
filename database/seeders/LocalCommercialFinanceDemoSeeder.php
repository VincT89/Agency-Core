<?php

namespace Database\Seeders;

use App\Domain\Finance\Actions\GenerateRecurringExpenses;
use App\Domain\Finance\Actions\SaveExpense;
use App\Domain\Finance\Services\CashAmount;
use App\Models\CashFlowSetting;
use App\Models\Client;
use App\Models\ExpenseDocument;
use App\Models\ExpenseRecurrence;
use App\Models\Invoice;
use App\Models\ManualIncome;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Services\InvoicePaymentSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Explicitly invoked local examples; deliberately excluded from DatabaseSeeder. */
class LocalCommercialFinanceDemoSeeder extends Seeder
{
    private const MARKER = 'demo-verifica-gestionale';

    private const NOTE = 'DEMO - Dati e importi inventati per la verifica locale del gestionale. Nessun valore fiscale o commerciale.';

    private array $createdFiles = [];

    private User $admin;

    private CarbonImmutable $today;

    public function run(): void
    {
        $connection = DB::connection();
        if (! app()->environment(['local', 'testing', 'browser_audit'])
            || ($connection->getDriverName() !== 'sqlite' && ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true))) {
            throw new RuntimeException('I dati dimostrativi possono essere inseriti solo in un database locale.');
        }
        if (Client::where('slug', self::MARKER.'-web')->exists()) {
            $this->command?->info('Esempi DEMO già presenti: nessun dato modificato o duplicato.');

            return;
        }
        if (! is_file(database_path('seeders/fixtures/documento-demo-gestionale.pdf'))) {
            throw new RuntimeException('Documento dimostrativo mancante.');
        }

        $this->admin = User::where('role', 'admin')->where('status', 'active')->orderBy('id')->firstOrFail();
        $commercial = User::where('role', 'commercial')->where('status', 'active')
            ->orderByRaw('CASE WHEN email = ? THEN 0 ELSE 1 END', ['commerciale@sodanoconsulting.it'])->orderBy('id')->firstOrFail();
        $this->today = CarbonImmutable::today();
        $previousUser = Auth::user();
        Auth::setUser($this->admin);

        try {
            Model::withoutEvents(fn () => DB::transaction(function () use ($commercial) {
                $clients = collect([
                    ['web', 'Sito web'], ['marketing', 'Marketing'], ['foto', 'Fotografia'],
                ])->map(fn ($data) => Client::create([
                    'slug' => self::MARKER.'-'.$data[0], 'name' => 'DEMO - Cliente '.$data[1],
                    'company_name' => 'DEMO - Azienda '.$data[1], 'commercial_user_id' => $commercial->id,
                    'email' => $data[0].'@demo.example.test', 'reference_person' => 'Referente dimostrativo',
                    'country' => 'Italia', 'country_code' => 'IT', 'status' => 'active',
                    'notes' => self::NOTE, 'commercial_notes' => self::NOTE,
                    'activity_description' => 'Anagrafica dimostrativa per provare offerte e storico cliente.',
                ]));

                $project = Project::create([
                    'client_id' => $clients[1]->id, 'name' => 'DEMO - Progetto da offerta accettata',
                    'slug' => self::MARKER.'-progetto', 'code' => 'DEMO-MKT', 'status' => 'active',
                    'description' => self::NOTE, 'start_date' => $this->today->subDays(7),
                    'end_date' => $this->today->addMonths(3), 'notes' => self::NOTE,
                ]);
                $project->users()->attach([$this->admin->id, $commercial->id], [
                    'role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now(),
                ]);
                foreach (['todo' => 'Preparare i materiali', 'in_progress' => 'Lavorazione in corso', 'done' => 'Attività completata'] as $status => $title) {
                    Task::create([
                        'project_id' => $project->id, 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id,
                        'title' => 'DEMO - '.$title, 'description' => self::NOTE, 'status' => $status, 'priority' => 'medium',
                        'due_date' => $this->today->addDays(10), 'completed_at' => $status === 'done' ? now() : null,
                    ]);
                }

                $this->quote($clients[0], 'Sito web in preparazione', 'draft', [['Analisi iniziale', '1', '350.00'], ['Realizzazione sito', '1', '1800.00']]);
                $this->quote($clients[0], 'Proposta sito web', 'presented', [['Realizzazione sito', '1', '2200.00'], ['Formazione', '2', '150.00']]);
                $this->quote($clients[1], 'Campagna marketing accettata', 'accepted', [['Gestione marketing', '6', '600.00']], ['project_id' => $project->id]);
                $rejected = $this->quote($clients[0], 'Prima proposta rifiutata', 'rejected', [['Proposta iniziale', '1', '3200.00']]);
                $this->quote($clients[0], 'Proposta rivista', 'presented', [['Proposta aggiornata', '1', '2800.00']], ['previous_quote_id' => $rejected->id, 'revision' => 2]);
                $photoQuote = $this->quote($clients[2], 'Servizio fotografico', 'presented', [['Sessione fotografica', '1', '650.00'], ['Postproduzione', '1', '200.00']]);
                $this->attachment($photoQuote, 'DEMO-brief-servizio.txt', self::NOTE."\n\nEsempio di allegato: obiettivi, materiali e indicazioni per il servizio fotografico.\n", 'text/plain');
                $this->attachment($clients[1], 'DEMO-logo-vettoriale.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="500" height="160"><rect width="500" height="160" fill="white"/><text x="32" y="100" font-family="sans-serif" font-size="64" fill="#15151a">DEMO</text></svg>', 'image/svg+xml', 'vector');
                $this->attachment($clients[1], 'DEMO-linee-guida.txt', self::NOTE."\n\nMateriale di esempio per provare ricerca, categorie e download nell'archivio cliente.\n", 'text/plain', 'guidelines');

                $this->invoices($clients->all(), $project);
                $this->expenses();
                foreach ([
                    ['Rimborso incassato', 'received', '75.00', -3],
                    ['Contributo previsto', 'expected', '500.00', 15],
                    ['Rimborso in ritardo', 'expected', '120.00', -5],
                    ['Entrata annullata', 'cancelled', '200.00', 20],
                    ['Contributo a sei mesi', 'expected', '1800.00', 180],
                    ['Contributo anno successivo', 'expected', '2500.00', 365],
                ] as [$title, $status, $amount, $days]) {
                    ManualIncome::create([
                        'user_id' => $this->admin->id, 'title' => 'DEMO - '.$title, 'payer' => 'DEMO - Soggetto dimostrativo',
                        'amount' => $amount, 'expected_on' => $this->today->addDays($days),
                        'received_on' => $status === 'received' ? $this->today : null, 'status' => $status, 'notes' => self::NOTE,
                    ]);
                }
                // Preserve a balance already configured by the user.
                CashFlowSetting::firstOrCreate(['id' => 1], [
                    'opening_balance' => '15000.00', 'balance_date' => $this->today->startOfMonth(), 'updated_by' => $this->admin->id,
                ]);
            }));
        } catch (Throwable $exception) {
            foreach ($this->createdFiles as $path) {
                Storage::disk('attachments')->delete($path);
            }
            throw $exception;
        } finally {
            $previousUser ? Auth::setUser($previousUser) : Auth::forgetUser();
        }

        $this->command?->info('Creati gli esempi DEMO: clienti, offerte, fatture, pagamenti, ricorrenze, spese, entrate e materiali.');
    }

    private function quote(Client $client, string $title, string $status, array $lines, array $extra = []): Quote
    {
        $quote = Quote::create($extra + [
            'client_id' => $client->id, 'created_by' => $this->admin->id, 'title' => 'DEMO - '.$title,
            'status' => $status, 'revision' => 1, 'notes' => self::NOTE,
            'client_snapshot' => $status === 'draft' ? null : $client->only(['name', 'company_name', 'email', 'reference_person', 'country', 'country_code']),
            'presented_at' => $status === 'draft' ? null : $this->today->subDays(8),
            'accepted_at' => $status === 'accepted' ? $this->today->subDays(7) : null,
            'rejected_at' => $status === 'rejected' ? $this->today->subDays(7) : null,
        ]);
        $total = 0;
        foreach ($lines as $index => [$name, $quantity, $price]) {
            $lineTotal = (int) $quantity * CashAmount::cents($price);
            $quote->items()->create([
                'name' => $name, 'description' => self::NOTE, 'quantity' => $quantity,
                'unit_price' => $price, 'total' => CashAmount::decimal($lineTotal), 'sort_order' => $index,
            ]);
            $total += $lineTotal;
        }
        $quote->update(['total' => CashAmount::decimal($total)]);

        return $quote;
    }

    private function invoices(array $clients, Project $project): void
    {
        foreach ([
            ['draft', 0, '500.00', 30, null], ['issued', 0, '1000.00', 30, null],
            ['issued', 1, '2000.00', 15, '1000.00'], ['issued', 2, '750.00', -2, '915.00'],
            ['issued', 1, '500.00', -7, null],
        ] as $index => [$status, $clientIndex, $subtotal, $days, $payment]) {
            $tax = intdiv(CashAmount::cents($subtotal) * 22, 100);
            $total = CashAmount::cents($subtotal) + $tax;
            $invoice = Invoice::create([
                'client_id' => $clients[$clientIndex]->id, 'project_id' => $clientIndex === 1 ? $project->id : null,
                'created_by' => $this->admin->id, 'number' => 'DEMO-'.$this->today->year.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'issue_date' => $this->today->subDays(20), 'due_date' => $this->today->addDays($days),
                'status' => $status, 'currency' => 'EUR', 'subtotal' => $subtotal, 'tax_amount' => CashAmount::decimal($tax),
                'total' => CashAmount::decimal($total), 'paid_total' => '0.00', 'fiscal_status' => 'not_prepared', 'notes' => self::NOTE,
            ]);
            $invoice->items()->create([
                'description' => 'DEMO - Prestazione dimostrativa', 'quantity' => '1', 'unit_price' => $subtotal,
                'total' => $subtotal, 'unit_of_measure' => 'N', 'vat_rate' => '22.00',
                'tax_amount' => CashAmount::decimal($tax), 'total_with_tax' => CashAmount::decimal($total),
            ]);
            if ($payment) {
                $invoice->payments()->create([
                    'client_id' => $invoice->client_id, 'project_id' => $invoice->project_id, 'created_by' => $this->admin->id,
                    'amount' => $payment, 'payment_date' => $this->today, 'method' => 'bank_transfer',
                    'reference' => 'DEMO-PAG-'.($index + 1), 'notes' => self::NOTE,
                ]);
            }
            app(InvoicePaymentSyncService::class)->sync($invoice);
        }
    }

    private function expenses(): void
    {
        $month = $this->today->startOfMonth();
        $end = $this->today->addMonthsNoOverflow(23)->endOfMonth();
        $recurrences = [];
        foreach ([
            ['Abbonamento software', 'monthly', '59.90', 'invoice', $month, $end, true, 'software'],
            ['Stipendio dimostrativo', 'monthly', '1450.00', 'payslip', $month->subDay(), $end, true, 'personale'],
            ['Licenza trimestrale', 'quarterly', '195.00', 'invoice', $month->addMonth(), $end, true, 'software'],
            ['Manutenzione semestrale', 'semiannual', '240.00', 'invoice', $month->addMonth()->addDays(14), $end, true, 'manutenzione'],
            ['Assicurazione annuale', 'yearly', '420.00', 'invoice', $month->addMonth(), $end, true, 'assicurazioni'],
            ['Servizio settimanale sospeso', 'weekly', '35.00', 'invoice', $this->today, $this->today->addWeeks(3), false, 'servizi'],
        ] as [$title, $frequency, $amount, $kind, $start, $finish, $active, $category]) {
            $recurrence = ExpenseRecurrence::create([
                'user_id' => $this->admin->id, 'title' => 'DEMO - '.$title, 'amount' => $amount,
                'category' => $category, 'supplier' => 'DEMO - Fornitore '.$title, 'document_kind' => $kind,
                'frequency' => $frequency, 'starts_on' => $start, 'ends_on' => $finish, 'active' => true, 'notes' => self::NOTE,
            ]);
            app(GenerateRecurringExpenses::class)->execute($recurrence);
            if (! $active) {
                $recurrence->update(['active' => false]);
                app(GenerateRecurringExpenses::class)->updateFuture($recurrence);
            }
            $recurrences[] = $recurrence;
        }
        foreach (array_slice($recurrences, 0, 2) as $index => $recurrence) {
            $expense = $recurrence->expenses()->orderBy('recurrence_date')->firstOrFail();
            $document = $this->document('RIC-'.($index + 1), $expense->document_kind, $expense->amount);
            app(SaveExpense::class)->execute(['expense_document_id' => $document->id, 'status' => 'paid', 'paid_at' => $this->today], $expense);
        }
        foreach ([
            ['Acquisto periferiche', '219.60', 'paid', -2, 'invoice', true, 'attrezzature'],
            ['Materiale promozionale', '380.00', 'pending', 15, 'invoice', true, 'marketing'],
            ['Trasferta da rimborsare', '85.00', 'pending', -5, 'receipt', true, 'trasferte'],
            ['Noleggio annullato', '160.00', 'cancelled', 7, 'invoice', false, 'attrezzature'],
            ['Consulenza esterna prevista', '320.00', 'pending', 45, 'invoice', false, 'consulenze'],
        ] as $index => [$title, $amount, $status, $days, $kind, $hasDocument, $category]) {
            $document = $hasDocument ? $this->document('USC-'.($index + 1), $kind, $amount) : null;
            app(SaveExpense::class)->execute([
                'title' => 'DEMO - '.$title, 'description' => self::NOTE, 'amount' => $amount, 'category' => $category,
                'supplier' => 'DEMO - Fornitore dimostrativo', 'expense_date' => $this->today, 'due_date' => $this->today->addDays($days),
                'status' => $status, 'paid_at' => $status === 'paid' ? $this->today : null,
                'document_kind' => $kind, 'expense_document_id' => $document?->id, 'notes' => self::NOTE,
            ]);
        }
        $this->document('DA-COLLEGARE', 'invoice', '290.00');
    }

    private function document(string $reference, string $kind, string $amount): ExpenseDocument
    {
        $number = 'DEMO-'.$reference;
        $filename = $number.'.pdf';
        $path = $this->writeFile($filename, file_get_contents(database_path('seeders/fixtures/documento-demo-gestionale.pdf')));
        $data = [
            'user_id' => $this->admin->id, 'kind' => $kind, 'issuer' => 'DEMO - Emittente dimostrativo', 'issuer_country' => 'IT',
            'number' => $number, 'document_date' => $this->today->subDays(10)->toDateString(),
            'due_date' => $this->today->addDays(15), 'amount' => $amount, 'currency' => 'EUR',
            'disk' => 'attachments', 'path' => $path, 'original_name' => $filename,
        ];

        return ExpenseDocument::create($data + ['fingerprint' => ExpenseDocument::fingerprintFor($data)]);
    }

    private function attachment(Model $model, string $filename, string $content, string $mimeType, ?string $category = null): void
    {
        $path = $this->writeFile($filename, $content);
        $model->attachments()->create([
            'type' => 'document', 'uploaded_by' => $this->admin->id, 'disk' => 'attachments',
            'directory' => dirname($path), 'path' => $path, 'original_name' => $filename, 'stored_name' => $filename,
            'mime_type' => $mimeType, 'extension' => pathinfo($filename, PATHINFO_EXTENSION), 'size' => strlen($content),
            'description' => self::NOTE, 'client_material_category' => $category,
        ]);
    }

    private function writeFile(string $filename, string $content): string
    {
        $path = self::MARKER.'/'.$filename;
        $disk = Storage::disk('attachments');
        if ($disk->exists($path)) {
            throw new RuntimeException('File dimostrativo già presente: '.$filename);
        }
        if (! $disk->put($path, $content)) {
            throw new RuntimeException('Impossibile salvare il documento dimostrativo.');
        }
        $this->createdFiles[] = $path;

        return $path;
    }
}
