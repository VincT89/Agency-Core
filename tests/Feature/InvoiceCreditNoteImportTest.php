<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Domain\Finance\Services\CashFlowForecast;
use App\Domain\Finance\Services\InvoiceImportArchive;
use App\Domain\Finance\Services\InvoiceImportBatch;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\UserRole;
use App\Livewire\Invoices\InvoiceBatchImport;
use App\Livewire\Invoices\InvoiceImport;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\EconomicSummaryService;
use App\Services\FinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;
use ZipArchive;

class InvoiceCreditNoteImportTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Cache::flush();
        Storage::fake('attachments');
        $this->travelTo(now()->setDate(2026, 10, 16));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['name' => 'Anagrafica da conservare', 'vat_number' => '01879020517', 'tax_code' => null, 'country_code' => 'IT']);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function files(array $xmls): array
    {
        return array_map(fn ($xml, $index) => ['filename' => "documento-$index.xml", 'content' => $xml, 'format' => 'xml', 'source' => 'file'], $xmls, array_keys($xmls));
    }

    private function import(string $number = 'FPR 1/26', string $type = 'TD01', bool $split = false): Invoice|CreditNote
    {
        $xml = InvoiceImportXml::make($number, $type, $split, $type === 'TD04' ? 'Storno fattura 1/26' : 'Servizio dimostrativo');
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[0];

        return app(ImportIssuedInvoice::class)->execute($document, $this->files([$xml])[0], $this->client->id, false, $document['due_date']);
    }

    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-zip-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            foreach ($entries as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return UploadedFile::fake()->createWithContent('esportazione.zip', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function snapshot(): array
    {
        $tables = ['invoices', 'credit_notes', 'credit_note_allocations', 'clients', 'payments', 'attachments', 'audit_logs'];

        return array_combine($tables, array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables));
    }

    public function test_zip_preview_has_no_writes_and_confirmed_import_preserves_numbers_and_separates_credit_notes(): void
    {
        $zip = $this->zip(['XML/fpa.xml' => InvoiceImportXml::make('FPA 1/26', split: true),
            'XML/fpr.xml' => InvoiceImportXml::make(), 'XML/nota.xml' => InvoiceImportXml::make('FPR 17/26', 'TD04')]);
        $before = $this->snapshot();
        $component = Livewire::test(InvoiceBatchImport::class)->set('file', $zip)->call('preview')->assertHasNoErrors()->assertCount('rows', 3);
        $this->assertSame($before, $this->snapshot());
        $component->call('import')->assertHasErrors('confirmed');
        $component->set('confirmed', true)->call('import')->assertHasNoErrors()->assertSet('completed', true)->assertSee('3 importati');
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('credit_notes', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('credit_note_allocations', 0);
        $this->assertDatabaseCount('attachments', 3);
        $this->assertEqualsCanonicalizing(['FPA 1/26', 'FPR 1/26'], Invoice::all()->pluck('display_number')->all());
        $this->assertSame(['issued'], Invoice::all()->pluck('status')->unique()->values()->all());
        $this->get(route('invoices.index'))->assertOk()->assertSee('FPA 1/26')->assertSee('FPR 1/26')->assertSee('FPR 17/26')->assertSee('TD04');
        $this->get(route('credit-notes.index'))->assertOk()->assertSee('FPR 17/26')->assertSee('TD04');
        $component->call('import')->assertHasErrors('file');
        Http::assertNothingSent();
    }

    public function test_repeat_import_preserves_all_rows_files_payments_and_client_data(): void
    {
        $files = $this->files([InvoiceImportXml::make(), InvoiceImportXml::make('FPR 17/26', 'TD04')]);
        $service = app(InvoiceImportBatch::class);
        $service->import($files);
        $invoice = Invoice::sole();
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '50.00', 'payment_date' => '2026-10-16', 'method' => 'bank_transfer']);
        app(AllocateCreditNote::class)->execute(CreditNote::sole(), $invoice->id, '20.00');
        $invoice->refresh()->update(['notes' => 'Nota locale da conservare']);
        $before = $this->snapshot();
        $paths = Storage::disk('attachments')->allFiles();
        $files[0]['filename'] = 'nuovo-nome.xml';
        $files[0]['content'] = str_replace(['<ProgressivoInvio>1', 'p:', 'xmlns:p=', '><'], ['<ProgressivoInvio>99', 'q:', 'xmlns:q=', ">\n<"], $files[0]['content']);
        $rows = $service->import($files);
        $this->assertSame(['skipped', 'skipped'], array_column($rows, 'status'));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($paths, Storage::disk('attachments')->allFiles());
        $this->assertSame('Anagrafica da conservare', $this->client->fresh()->name);
        $this->assertSame('50.00', $invoice->fresh()->paid_total);
    }

    public function test_conflicting_content_with_same_totals_is_reported_without_overwriting_even_for_legacy_imports(): void
    {
        $invoice = $this->import();
        foreach ([false, true] as $legacy) {
            if ($legacy) {
                $metadata = $invoice->import_metadata;
                unset($metadata['content_fingerprint']);
                $invoice->update(['import_metadata' => $metadata]);
            }
            $before = $this->snapshot();
            $rows = app(InvoiceImportBatch::class)->import($this->files([InvoiceImportXml::make(description: 'Descrizione modificata') ]));
            $this->assertSame('error', $rows[0]['status']);
            $this->assertStringContainsString('Nessun dato', $rows[0]['message']);
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_a_conflicting_document_does_not_prevent_other_new_documents_from_importing(): void
    {
        $invoice = $this->import();
        $before = $invoice->fresh()->getRawOriginal();
        $rows = app(InvoiceImportBatch::class)->import($this->files([
            InvoiceImportXml::make(description: 'Conflitto'), InvoiceImportXml::make('FPR 2/26'), '<invalid/>',
        ]));
        $this->assertSame(['error', 'imported', 'error'], array_column($rows, 'status'));
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_same_number_in_different_year_is_allowed_but_conflicting_document_type_is_not(): void
    {
        $this->import();
        $rows = app(InvoiceImportBatch::class)->import($this->files([str_replace('2026-', '2025-', InvoiceImportXml::make()), InvoiceImportXml::make(type: 'TD04')]));
        $this->assertSame(['imported', 'error'], array_column($rows, 'status'));
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_unknown_client_is_created_once_and_ambiguous_clients_are_not_guessed(): void
    {
        $xml = str_replace('01879020517', '12345678903', InvoiceImportXml::make());
        $files = $this->files([$xml, str_replace('FPR 1/26', 'FPR 2/26', $xml)]);
        app(InvoiceImportBatch::class)->preview($files);
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame(['imported', 'imported'], array_column(app(InvoiceImportBatch::class)->import($files), 'status'));
        $this->assertDatabaseCount('clients', 2);
        Client::factory()->create(['vat_number' => '01879020517', 'tax_code' => null, 'country_code' => 'IT']);
        $rows = app(InvoiceImportBatch::class)->import($this->files([InvoiceImportXml::make('FPR 3/26')]));
        $this->assertSame('error', $rows[0]['status']);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_split_payment_uses_net_receivable_in_payments_summaries_and_forecast(): void
    {
        $invoice = $this->import('FPA 1/26', split: true);
        $this->assertSame('122.00', $invoice->total);
        $this->assertSame('100.00', $invoice->payable_total);
        $this->assertEquals(100, $invoice->residual);
        $this->assertEquals(122, app(EconomicSummaryService::class)->globalSummary(auth()->user())['total_outstanding']);
        $this->assertEquals([0, 100], app(FinancialSummaryService::class)->getYearlyDonutData()['series']);
        $this->assertSame(10000, app(CashFlowForecast::class)->calculate(3, null)['totals']['expected']);
        $data = ['invoice_id' => $invoice->id, 'amount' => '100.01', 'payment_date' => '2026-10-16', 'method' => 'bank_transfer'];
        $this->post(route('payments.store'), $data)->assertSessionHasErrors('amount');
        $this->post(route('payments.store'), array_replace($data, ['amount' => '100.00']))->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->residual);
        $this->assertSame('122.00', $invoice->fresh()->total);
    }

    public function test_credit_note_references_are_suggestions_and_multiple_allocations_reduce_residual_without_payments(): void
    {
        $first = $this->import();
        $second = $this->import('FPR 2/26');
        $note = $this->import('FPR 17/26', 'TD04');
        $this->get(route('credit-notes.show', $note))->assertOk()->assertSee('Verifica FPR 1/26')->assertSee('Nessuna fattura collegata');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $first->id, 'amount' => '100.00'])->assertSessionHasNoErrors();
        $before = $this->snapshot();
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $first->id, 'amount' => '100.00'])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->snapshot());
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $second->id, 'amount' => '22.01'])->assertSessionHasErrors('amount');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $second->id, 'amount' => '22.00'])->assertSessionHasNoErrors();
        $this->assertEquals(22, $first->fresh()->residual);
        $this->assertEquals(100, $second->fresh()->residual);
        $this->assertEquals(0, $note->fresh()->unallocated_total);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('invoices.show', $first))->assertOk()->assertSee('FPR 17/26');
        $this->delete(route('credit-notes.allocations.destroy', [$note, $note->allocations()->where('invoice_id', $first->id)->first()]))->assertSessionHasNoErrors();
        $this->assertEquals(122, $first->fresh()->residual);
        $this->assertEquals(100, $note->fresh()->unallocated_total);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('credit_notes', 1);
    }

    public function test_crediting_a_paid_invoice_preserves_payments_and_exposes_customer_credit(): void
    {
        $invoice = $this->import();
        $note = $this->import('FPR 17/26', 'TD04');
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '122.00', 'payment_date' => '2026-10-16', 'method' => 'bank_transfer']);
        $payment = $invoice->payments()->sole()->getRawOriginal();
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '122.00');
        $this->assertSame('credited', $invoice->fresh()->status);
        $this->assertSame('122.00', $invoice->fresh()->paid_total);
        $this->assertEquals(122, $invoice->fresh()->customer_credit);
        $this->assertSame($payment, $invoice->payments()->sole()->getRawOriginal());
        $summary = app(EconomicSummaryService::class)->globalSummary(auth()->user());
        $this->assertEquals(0, $summary['total_invoiced']);
        $this->assertEquals(0, $summary['total_outstanding']);
        $this->assertEquals(122, $summary['total_collected']);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Credito cliente');
        $receipt = $invoice->payments()->sole();
        $data = ['amount' => '122.00', 'payment_date' => '2026-10-17', 'method' => 'bank_transfer', 'reference' => 'Riferimento corretto'];
        $this->put(route('payments.update', $receipt), $data)->assertSessionHasNoErrors();
        $this->assertSame('122.00', $invoice->fresh()->paid_total);
        $this->put(route('payments.update', $receipt), array_replace($data, ['amount' => '123.00']))->assertSessionHasErrors('amount');
    }

    public function test_credit_notes_reduce_net_turnover_in_their_issue_period_while_links_reduce_receivables(): void
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $invoice = $this->import();
        $invoice->update(['project_id' => $project->id]);
        $note = $this->import('FPR 17/26', 'TD04');
        $note->update(['issue_date' => '2026-11-01']);
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '122.00');
        $service = app(EconomicSummaryService::class);
        $this->assertEquals(100, $service->globalSummary(auth()->user(), '2026-10-01', '2026-10-31')['total_invoiced']);
        $this->assertEquals(0, $service->globalSummary(auth()->user(), '2026-10-01', '2026-10-31')['total_outstanding']);
        $this->assertEquals(-100, $service->globalSummary(auth()->user(), '2026-11-01', '2026-11-30')['total_invoiced']);
        $this->assertEquals(-100, $service->summaryByClient(auth()->user(), '2026-11-01', '2026-11-30')->sole()->total_invoiced);
        $this->assertEquals(-100, $service->summaryByProject(auth()->user(), '2026-11-01', '2026-11-30')->sole()->total_invoiced);
        $monthly = app(FinancialSummaryService::class)->getMonthlyData()['series'][0]['data'];
        $this->assertEquals(100, $monthly[9]);
        $this->assertEquals(-100, $monthly[10]);
    }

    public function test_allocation_rejects_wrong_clients_drafts_excesses_and_foreign_allocation_ids(): void
    {
        $invoice = $this->import();
        $note = $this->import('FPR 17/26', 'TD04');
        foreach ([['client_id' => Client::factory()->create()->id], ['status' => 'draft'], ['currency' => 'USD']] as $changes) {
            $original = $invoice->only(array_keys($changes));
            $invoice->update($changes);
            $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '1.00'])->assertSessionHasErrors('invoice_id');
            $invoice->update($original);
        }
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '122.01'])->assertSessionHasErrors('amount');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '-1'])->assertSessionHasErrors('amount');
        $allocation = app(AllocateCreditNote::class)->execute($note, $invoice->id, '10.00');
        $otherNote = $this->import('FPR 18/26', 'TD04');
        $this->delete(route('credit-notes.allocations.destroy', [$otherNote, $allocation]))->assertNotFound();
        $this->assertDatabaseCount('credit_note_allocations', 1);
    }

    public function test_original_xml_is_downloadable_but_cannot_be_deleted_even_by_admin(): void
    {
        $note = $this->import('FPR 17/26', 'TD04');
        $attachment = $note->attachments()->sole();
        $this->assertFalse(Gate::allows('delete', $attachment));
        $this->assertTrue(Gate::allows('download', $attachment));
        $this->get(route('attachments.download', $attachment))->assertOk();
        $this->assertSame(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26'), Storage::disk('attachments')->get($attachment->path));
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        foreach (['invoices.import.archive', 'credit-notes.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('credit-notes.show', $note))->assertForbidden();
        $this->get(route('attachments.download', $attachment))->assertForbidden();
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => 1, 'amount' => '10.00'])->assertForbidden();
    }

    public function test_batch_cache_is_bound_to_owner_and_expired_preview_cannot_write(): void
    {
        $component = Livewire::test(InvoiceBatchImport::class)->set('file', $this->zip(['a.xml' => InvoiceImportXml::make()]))->call('preview')->assertHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $component->set('confirmed', true)->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_single_xml_import_redirects_credit_notes_to_the_separate_detail(): void
    {
        $component = Livewire::test(InvoiceImport::class)->set('file', UploadedFile::fake()->createWithContent('nota.xml', InvoiceImportXml::make('FPR 17/26', 'TD04')))
            ->call('previewFile')->assertHasNoErrors()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $component->assertRedirect(route('credit-notes.show', CreditNote::sole()));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_large_preview_uses_bounded_database_cache_entries_and_cleans_them_after_import(): void
    {
        config(['cache.default' => 'database']);
        $xml = str_replace('</p:FatturaElettronica>', '<!--'.base64_encode(random_bytes(600000)).'--></p:FatturaElettronica>', InvoiceImportXml::make());
        $component = Livewire::test(InvoiceBatchImport::class)->set('file', $this->zip(['a.xml' => $xml]))->call('preview')->assertHasNoErrors();
        $key = 'invoice-batch:'.auth()->id().':'.$component->get('token');
        $count = Cache::get($key);
        $this->assertGreaterThan(1, $count);
        for ($index = 0; $index < $count; $index++) {
            $this->assertLessThanOrEqual(512 * 1024, strlen(Cache::get($key.':'.$index)));
        }
        $component->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertFalse(Cache::has($key));
        $this->assertFalse(Cache::has($key.':0'));
        $attachment = Invoice::sole()->attachments()->sole();
        $this->assertSame($xml, Storage::disk('attachments')->get($attachment->path));
    }

    public function test_missing_preview_chunk_cannot_partially_import_a_batch(): void
    {
        $component = Livewire::test(InvoiceBatchImport::class)->set('file', $this->zip(['a.xml' => InvoiceImportXml::make()]))->call('preview')->assertHasNoErrors();
        Cache::forget('invoice-batch:'.auth()->id().':'.$component->get('token').':0');
        $component->set('confirmed', true)->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('invoices', 0);
    }

    public static function unsafeArchives(): array
    {
        return [
            'traversal' => ['../documento.xml', '<x/>'],
            'absolute path' => ['C:/documento.xml', '<x/>'],
            'executable' => ['file.php', '<?php exit;'],
            'compression bomb' => ['large.xml', str_repeat('A', 500000)],
        ];
    }

    public function test_many_small_xml_files_allocate_memory_for_their_actual_size(): void
    {
        $entries = [];
        for ($index = 0; $index < 40; $index++) {
            $entries["XML/documento-$index.xml"] = InvoiceImportXml::make('FPR '.($index + 1).'/26');
        }
        $file = $this->zip($entries);
        $before = memory_get_usage();
        $files = app(InvoiceImportArchive::class)->files($file->getRealPath());
        $this->assertCount(40, $files);
        $this->assertLessThan(8 * 1024 * 1024, memory_get_usage() - $before);
    }

    #[DataProvider('unsafeArchives')]
    public function test_unsafe_archives_are_rejected_without_extraction(string $name, string $content): void
    {
        $file = $this->zip([$name => $content]);
        $this->expectException(ValidationException::class);
        app(InvoiceImportArchive::class)->files($file->getRealPath());
    }
}
