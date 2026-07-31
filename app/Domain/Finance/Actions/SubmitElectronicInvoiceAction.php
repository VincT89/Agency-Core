<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\DTOs\FatturaPaXmlDocument;
use App\Domain\Finance\Services\FatturaPaXmlBuilder;
use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Exceptions\Finance\ArubaApiException;
use App\Exceptions\Finance\ElectronicInvoiceSubmissionException;
use App\Models\ElectronicInvoiceTransmission;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use Illuminate\Support\Facades\DB;

class SubmitElectronicInvoiceAction
{
    public function __construct(
        private readonly FatturaPaXmlBuilder $xmlBuilder,
        private readonly ArubaConfiguration $configuration,
        private readonly ArubaInvoiceClient $client,
    ) {}

    public function execute(
        Invoice $invoice,
        User $submittedBy,
        bool $dryRun,
    ): ElectronicInvoiceTransmission {
        $this->configuration->assertCanUpload($dryRun);

        [$transmission, $xml, $alreadyCompleted] = DB::transaction(
            fn (): array => $this->reserveAttempt($invoice, $submittedBy, $dryRun),
        );

        if ($alreadyCompleted) {
            return $transmission;
        }

        try {
            $result = $this->client->upload($xml, $dryRun);
            $status = $dryRun
                ? ElectronicInvoiceTransmissionStatus::Validated
                : ElectronicInvoiceTransmissionStatus::TakenCharge;

            $transmission->update([
                'status' => $status,
                'request_identifier' => $result['request_identifier'],
                'upload_filename' => $result['upload_filename'],
                'provider_status' => $dryRun
                    ? 'Controlli Aruba superati'
                    : 'Presa in carico da Aruba',
                'error_code' => $result['error_code'],
                'error_message' => null,
                'response_payload' => $result['response_payload'],
                'last_status_at' => now(),
                'completed_at' => $dryRun ? now() : null,
            ]);

            if (! $dryRun) {
                Invoice::query()
                    ->whereKey($invoice->getKey())
                    ->where('fiscal_status', InvoiceFiscalStatus::Transmitting->value)
                    ->update(['fiscal_status' => InvoiceFiscalStatus::Sent->value]);
            }

            return $transmission->fresh();
        } catch (ArubaApiException $exception) {
            $fallbackFilename = $exception->uncertain
                ? (data_get($exception->responsePayload, 'uploadFileName')
                    ?: $transmission->xml_filename)
                : $transmission->upload_filename;

            $transmission->update([
                'status' => $exception->uncertain
                    ? ElectronicInvoiceTransmissionStatus::Uncertain
                    : ElectronicInvoiceTransmissionStatus::Failed,
                'provider_status' => $exception->uncertain
                    ? 'Esito da verificare'
                    : 'Operazione non completata',
                'upload_filename' => $fallbackFilename,
                'error_code' => $exception->providerCode,
                'error_message' => $exception->userMessage,
                'response_payload' => $exception->responsePayload,
                'last_status_at' => now(),
                'completed_at' => $exception->uncertain ? null : now(),
            ]);

            if (! $dryRun && ! $exception->uncertain) {
                Invoice::query()
                    ->whereKey($invoice->getKey())
                    ->where('fiscal_status', InvoiceFiscalStatus::Transmitting->value)
                    ->update(['fiscal_status' => InvoiceFiscalStatus::Ready->value]);
            }

            throw $exception;
        }
    }

    /**
     * @return array{ElectronicInvoiceTransmission, FatturaPaXmlDocument, bool}
     */
    private function reserveAttempt(
        Invoice $invoice,
        User $submittedBy,
        bool $dryRun,
    ): array {
        $lockedInvoice = Invoice::query()
            ->whereKey($invoice->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedInvoice->fiscal_status !== InvoiceFiscalStatus::Ready) {
            throw new ElectronicInvoiceSubmissionException(
                'La fattura non è nello stato corretto per questa operazione.'
            );
        }

        if (! is_array($lockedInvoice->fiscal_snapshot)) {
            throw new ElectronicInvoiceSubmissionException(
                'La versione fiscale bloccata non è disponibile. Riapri e prepara nuovamente la fattura.'
            );
        }

        $xml = $this->xmlBuilder->build($lockedInvoice->fiscal_snapshot);
        $environment = $this->configuration->environment();
        $mode = $dryRun ? 'dry_run' : 'live';

        if ($dryRun) {
            $validated = $lockedInvoice->electronicInvoiceTransmissions()
                ->where('environment', $environment)
                ->where('mode', 'dry_run')
                ->where('xml_hash', $xml->hash)
                ->where('status', ElectronicInvoiceTransmissionStatus::Validated->value)
                ->latest('id')
                ->first();

            if ($validated !== null) {
                return [$validated, $xml, true];
            }
        }

        $processing = $lockedInvoice->electronicInvoiceTransmissions()
            ->where('mode', $mode)
            ->where('status', ElectronicInvoiceTransmissionStatus::Processing->value)
            ->exists();

        if ($processing) {
            throw new ElectronicInvoiceSubmissionException(
                $dryRun
                    ? 'Una verifica con Aruba è già in corso.'
                    : 'L’invio ad Aruba è già in corso.'
            );
        }

        if (! $dryRun && $this->configuration->requireDryRun()) {
            $validated = $lockedInvoice->electronicInvoiceTransmissions()
                ->where('environment', $environment)
                ->where('mode', 'dry_run')
                ->where('xml_hash', $xml->hash)
                ->where('status', ElectronicInvoiceTransmissionStatus::Validated->value)
                ->exists();

            if (! $validated) {
                throw new ElectronicInvoiceSubmissionException(
                    'La fattura non risulta verificata da Aruba.'
                );
            }
        }

        $attemptNumber = (int) $lockedInvoice->electronicInvoiceTransmissions()
            ->max('attempt_number') + 1;

        $transmission = $lockedInvoice->electronicInvoiceTransmissions()->create([
            'submitted_by' => $submittedBy->getKey(),
            'provider' => 'aruba',
            'environment' => $environment,
            'mode' => $mode,
            'attempt_number' => $attemptNumber,
            'status' => ElectronicInvoiceTransmissionStatus::Processing,
            'xml_filename' => $xml->filename,
            'xml_hash' => $xml->hash,
            'xml_content' => $xml->content,
            'submitted_at' => now(),
        ]);

        if (! $dryRun) {
            $lockedInvoice->update([
                'fiscal_status' => InvoiceFiscalStatus::Transmitting,
            ]);
        }

        return [$transmission, $xml, false];
    }
}
