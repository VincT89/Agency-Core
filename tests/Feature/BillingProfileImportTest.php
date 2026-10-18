<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Invoices\InvoiceBatchImport;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;
use ZipArchive;

class BillingProfileImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('attachments');
        Cache::flush();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
    }

    private function profileData(array $overrides = []): array
    {
        return array_replace(['legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT',
            'default_payment_method' => 'MP05', 'invoice_series' => 'FE', 'initial_sequence' => 1, 'iban' => ''], $overrides);
    }

    private function zip(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'billing-import-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('fattura.xml', InvoiceImportXml::make());
            $zip->addFromString('fattura-pa.xml', InvoiceImportXml::make('FPA 1/26', split: true));
            $zip->addFromString('nota.xml', InvoiceImportXml::make('FPR 17/26', 'TD04'));
            $zip->close();

            return UploadedFile::fake()->createWithContent('esportazione.zip', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function snapshot(): array
    {
        $tables = ['billing_profiles', 'clients', 'invoices', 'credit_notes', 'credit_note_allocations', 'payments', 'attachments', 'audit_logs'];

        return array_combine($tables, array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables));
    }

    public function test_first_profile_saved_through_the_form_without_iban_allows_zip_import_and_safe_repeat_import(): void
    {
        $this->assertDatabaseCount('billing_profiles', 0);
        $this->get(route('billing-profile.edit'))->assertOk()->assertSee('IBAN');
        $this->put(route('billing-profile.update'), $this->profileData())
            ->assertSessionHasNoErrors()->assertRedirect(route('billing-profile.edit'));
        $this->assertNull(BillingProfile::sole()->iban);
        $this->assertSame('MP05', BillingProfile::sole()->default_payment_method);
        $this->get(route('billing-profile.edit'))->assertOk()->assertSee('Facoltativo per importare');

        $component = Livewire::test(InvoiceBatchImport::class)->set('file', $this->zip())
            ->call('preview')->assertHasNoErrors()->assertCount('rows', 3);
        $this->assertSame(['pending'], collect($component->get('rows'))->pluck('status')->unique()->values()->all());
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('clients', 0);
        $component->call('import')->assertHasErrors('confirmed');
        $component->set('confirmed', true)->call('import')->assertHasNoErrors()->assertSet('completed', true)
            ->assertSee('3 importati, 0 già presenti, 0 da verificare');
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('credit_notes', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('credit_note_allocations', 0);
        $this->assertDatabaseCount('attachments', 3);
        $this->assertSame(['issued'], Invoice::all()->pluck('status')->unique()->values()->all());
        $this->assertNull(BillingProfile::sole()->iban);

        $before = $this->snapshot();
        Livewire::test(InvoiceBatchImport::class)->set('file', $this->zip())->call('preview')
            ->set('confirmed', true)->call('import')->assertHasNoErrors()->assertSee('0 importati, 3 già presenti, 0 da verificare');
        $this->assertSame($before, $this->snapshot());
        Http::assertNothingSent();
    }

    public function test_optional_iban_does_not_bypass_field_validation_or_profile_permissions(): void
    {
        foreach (['iban' => 'non-valido', 'vat_number' => '01234567890', 'default_payment_method' => 'INVALID'] as $field => $invalid) {
            $this->put(route('billing-profile.update'), $this->profileData([$field => $invalid]))->assertSessionHasErrors($field);
            $this->assertDatabaseCount('billing_profiles', 0);
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->put(route('billing-profile.update'), $this->profileData())->assertForbidden();
        $this->assertDatabaseCount('billing_profiles', 0);
    }
}
