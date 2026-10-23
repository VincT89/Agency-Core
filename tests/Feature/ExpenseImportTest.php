<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\SaveExpenseCategory;
use App\Domain\Finance\Actions\StoreExpenseDocument;
use App\Domain\Finance\Services\ExpenseImportBatch;
use App\Domain\Finance\Services\ExpenseImportReader;
use App\Livewire\Expenses\ExpenseImport;
use App\Models\BillingProfile;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;
use ZipArchive;

class ExpenseImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password_changed_at' => now()]));
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Destinatario dimostrativo', 'vat_country_code' => 'IT',
            'vat_number' => '01879020517', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1', 'postal_code' => '80100',
            'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function rows(string $content, string $name = 'spese.csv'): array
    {
        $upload = UploadedFile::fake()->createWithContent($name, $content);
        return app(ExpenseImportReader::class)->read($upload->getRealPath(), $name);
    }

    private function workbook(string $format, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'expense-test-');
        $book = new Spreadsheet;
        try {
            $book->getActiveSheet()->fromArray($rows);
            IOFactory::createWriter($book, $format)->save($path);
            return file_get_contents($path);
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    private function archive(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'expense-zip-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            foreach ($files as $name => $content) $zip->addFromString($name, $content);
            $zip->close();
            return file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    public function test_csv_preview_is_read_only_and_reimports_preserve_later_edits(): void
    {
        app(SaveExpenseCategory::class)->execute(['name' => 'Software']);
        $rows = $this->rows("titolo;importo;data_spesa;fornitore;categoria;riferimento\nLicenza dimostrativa;1.234,56;23/10/2026;Fornitore dimostrativo;Software;LIC-1\n");
        $batch = app(ExpenseImportBatch::class);
        $this->assertSame('ready', $batch->process($rows)[0]['status']);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame('imported', $batch->process($rows, true)[0]['status']);
        $expense = Expense::sole();
        $this->assertSame('1234.56', $expense->amount);
        $this->assertSame('pending', $expense->status);
        $expense->update(['title' => 'Titolo corretto manualmente', 'notes' => 'Da conservare']);
        $this->assertSame('skipped', $batch->process($rows, true)[0]['status']);
        $this->assertSame('Titolo corretto manualmente', $expense->fresh()->title);
        $rows[0]['data']['amount'] = '2000.00';
        $this->assertSame('error', $batch->process($rows, true)[0]['status']);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_xls_and_xlsx_dates_and_cross_format_duplicates(): void
    {
        $table = [['titolo', 'importo', 'data_spesa', 'scadenza'], ['Costo dimostrativo Excel', 12.50, 46318, '23/11/2026']];
        foreach (['Xls', 'Xlsx'] as $index => $format) {
            $rows = $this->rows($this->workbook($format, $table), 'spese.'.strtolower($format));
            $this->assertSame('2026-10-23', $rows[0]['data']['expense_date']);
            $result = app(ExpenseImportBatch::class)->process($rows, true);
            $this->assertSame($index === 0 ? 'imported' : 'skipped', $result[0]['status']);
        }
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_xml_retains_original_document_and_zip_supports_mixed_formats(): void
    {
        $xml = InvoiceImportXml::make('FORNITORE-DEMO');
        $zip = $this->archive(['fatture/fattura.xml' => $xml, 'costi.csv' => "titolo,importo,data_spesa\nCosto CSV,10.00,2026-10-23\n"]);
        $rows = $this->rows($zip, 'spese.zip');
        $this->assertCount(2, $rows);
        $this->assertSame(['imported', 'imported'], array_column(app(ExpenseImportBatch::class)->process($rows, true), 'status'));
        $document = ExpenseDocument::sole();
        $this->assertSame($xml, Storage::disk($document->disk)->get($document->path));
        $this->assertSame('122.00', $document->expenses()->sole()->amount);
        $this->assertSame('pending', $document->expenses()->sole()->status);
        $this->assertSame(['skipped', 'skipped'], array_column(app(ExpenseImportBatch::class)->process($rows, true), 'status'));
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_rows_need_existing_proof_and_never_silently_invent_a_payment_date(): void
    {
        $rows = $this->rows("titolo;importo;data_spesa;stato;data_pagamento\nCosto pagato;25;2026-10-22;pagata;2026-10-23\n");
        $batch = app(ExpenseImportBatch::class);
        $this->assertSame('error', $batch->process($rows, true)[0]['status']);
        $document = app(StoreExpenseDocument::class)->execute(['kind' => 'receipt', 'issuer' => 'Fornitore dimostrativo', 'number' => 'R-1',
            'document_date' => '2026-10-22', 'amount' => 25], 'Documento dimostrativo del test', 'ricevuta.txt');
        $rows[0]['data'] += ['expense_document_id' => $document->id, 'document_kind' => 'receipt'];
        $this->assertSame('imported', $batch->process($rows, true)[0]['status']);
        $this->assertSame('2026-10-23', Expense::sole()->paid_at->format('Y-m-d'));
        $this->assertSame('paid', Expense::sole()->status);
    }

    public function test_invalid_rows_and_existing_manual_expenses_are_reported_without_overwriting(): void
    {
        Expense::create(['user_id' => auth()->id(), 'title' => 'Gia presente', 'amount' => 25, 'expense_date' => '2026-10-23', 'status' => 'pending']);
        $rows = $this->rows("titolo;importo;data_spesa;categoria\nGia presente;25;2026-10-23;\nData errata;12;31/02/2026;\nCategoria assente;12;2026-10-23;Inesistente\nDecimali;1,234;2026-10-23;\nValida;10;2026-10-23;\n");
        $this->assertSame(['error', 'error', 'error', 'error', 'imported'], array_column(app(ExpenseImportBatch::class)->process($rows, true), 'status'));
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_unsafe_archives_xml_and_workbook_formulas_are_rejected(): void
    {
        $cases = [
            ['bad.zip', $this->archive(['../costs.csv' => 'titolo,importo,data_spesa'])],
            ['nested.zip', $this->archive(['inside.zip' => 'invalid'])],
            ['large.zip', $this->archive(['costs.csv' => str_repeat('a', 100000)])],
            ['bad.xml', '<!DOCTYPE test [<!ENTITY data SYSTEM "file:///not-used">]><test>&data;</test>'],
            ['note.xml', InvoiceImportXml::make('NC-DEMO', 'TD04')],
            ['formula.xlsx', $this->workbook('Xlsx', [['titolo', 'importo', 'data_spesa'], ['Formula', '=1+1', '2026-10-23']])],
            ['fake.xls', '<html>File non Excel</html>'],
        ];
        foreach ($cases as [$name, $content]) {
            try { $this->rows($content, $name); $this->fail('File non rifiutato: '.$name); }
            catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_livewire_requires_preview_confirmation_and_finance_permissions(): void
    {
        $upload = UploadedFile::fake()->createWithContent('spese.csv', "titolo;importo;data_spesa\nCosto interfaccia;12,50;2026-10-23\n");
        $component = Livewire::test(ExpenseImport::class)->set('file', $upload)->call('preview')->assertHasNoErrors();
        $this->assertDatabaseCount('expenses', 0);
        $component->call('import')->assertHasErrors('confirmed')->set('confirmed', true)->call('import')->assertHasNoErrors()->assertSet('completed', true);
        $this->assertDatabaseCount('expenses', 1);
        $this->get(route('expenses.import'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'developer', 'password_changed_at' => now()]));
        $this->get(route('expenses.import'))->assertForbidden();
        Livewire::test(ExpenseImport::class)->assertForbidden();
    }
}
