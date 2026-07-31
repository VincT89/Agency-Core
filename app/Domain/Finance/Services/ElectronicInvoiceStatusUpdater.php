<?php

namespace App\Domain\Finance\Services;

use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\ElectronicInvoiceEvent;
use App\Models\ElectronicInvoiceTransmission;
use App\Models\User;
use App\Notifications\ElectronicInvoiceStatusNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class ElectronicInvoiceStatusUpdater
{
    public function applyDetail(
        ElectronicInvoiceTransmission $transmission,
        array $payload,
        string $source = 'pull',
    ): ElectronicInvoiceTransmission {
        $providerStatus = trim((string) data_get($payload, 'invoices.0.status', ''));
        $error = trim((string) data_get($payload, 'invoices.0.statusDescription', ''));
        $occurredAt = $this->date($payload['lastUpdate'] ?? $payload['creationDate'] ?? null);

        return $this->record(
            transmission: $transmission,
            status: $this->mapProviderStatus($providerStatus),
            source: $source,
            type: 'status',
            providerStatus: $providerStatus,
            providerFilename: $this->stringOrNull($payload['filename'] ?? null),
            sdiId: $this->stringOrNull($payload['idSdi'] ?? null),
            providerInvoiceId: $this->stringOrNull($payload['id'] ?? null),
            errorMessage: $error !== '' ? $error : null,
            payload: $payload,
            document: null,
            occurredAt: $occurredAt,
        );
    }

    public function applyStatusCallback(
        ElectronicInvoiceTransmission $transmission,
        array $payload,
    ): ElectronicInvoiceTransmission {
        $providerStatus = trim((string) ($payload['status'] ?? ''));
        $error = trim((string) ($payload['errorDescription'] ?? ''));

        return $this->record(
            transmission: $transmission,
            status: $this->mapProviderStatus($providerStatus),
            source: 'callback',
            type: 'status',
            providerStatus: $providerStatus,
            providerFilename: $this->stringOrNull($payload['invoiceFileName'] ?? null),
            sdiId: $this->stringOrNull($payload['sdiIdentification'] ?? null),
            providerInvoiceId: null,
            errorMessage: $error !== '' ? $error : null,
            payload: $payload,
            document: null,
            occurredAt: $this->date($payload['updateDate'] ?? null),
        );
    }

    public function applyNotification(
        ElectronicInvoiceTransmission $transmission,
        array $payload,
        string $source = 'pull',
    ): ElectronicInvoiceTransmission {
        $type = strtoupper(trim((string) ($payload['docType'] ?? $payload['notifyType'] ?? '')));
        $result = strtoupper(trim((string) ($payload['result'] ?? '')));
        $document = $this->decodeDocument(
            $payload['file'] ?? $payload['notifyXmlBase64'] ?? null
        );

        if ($type === 'NE' && $result === '') {
            $result = $this->notificationResultFromXml($document);
        }

        $providerStatus = $result !== '' ? "{$type} {$result}" : $type;

        return $this->record(
            transmission: $transmission,
            status: $this->mapNotification($type, $result),
            source: $source,
            type: 'notification',
            providerStatus: $providerStatus,
            providerFilename: $this->stringOrNull(
                $payload['filename'] ?? $payload['notifyFileName'] ?? null
            ),
            sdiId: $this->stringOrNull($payload['sdiIdentification'] ?? null),
            providerInvoiceId: $this->stringOrNull($payload['invoiceId'] ?? null),
            errorMessage: null,
            payload: $payload,
            document: $document,
            occurredAt: $this->date(
                $payload['notificationDate'] ?? $payload['date'] ?? null
            ),
        );
    }

    public function mapProviderStatus(string $status): ?ElectronicInvoiceTransmissionStatus
    {
        $normalized = Str::of($status)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        return match ($normalized) {
            'presa in carico' => ElectronicInvoiceTransmissionStatus::TakenCharge,
            'inviata' => ElectronicInvoiceTransmissionStatus::Sent,
            'consegnata' => ElectronicInvoiceTransmissionStatus::Delivered,
            'non consegnata' => ElectronicInvoiceTransmissionStatus::DeliveryFailed,
            'recapito impossibile' => ElectronicInvoiceTransmissionStatus::Undeliverable,
            'scartata' => ElectronicInvoiceTransmissionStatus::Rejected,
            'accettata' => ElectronicInvoiceTransmissionStatus::Accepted,
            'rifiutata' => ElectronicInvoiceTransmissionStatus::Refused,
            'decorrenza termini' => ElectronicInvoiceTransmissionStatus::TermsExpired,
            'errore elaborazione', 'errore di elaborazione' => ElectronicInvoiceTransmissionStatus::ProcessingError,
            default => null,
        };
    }

    public function mapNotification(
        string $type,
        string $result = '',
    ): ?ElectronicInvoiceTransmissionStatus {
        $normalizedType = strtoupper(str_replace('-', '_', trim($type)));
        $normalizedResult = strtoupper(trim($result));

        if (str_starts_with($normalizedType, 'NE_')) {
            $normalizedResult = substr($normalizedType, 3);
            $normalizedType = 'NE';
        }

        return match (true) {
            $normalizedType === 'NS' => ElectronicInvoiceTransmissionStatus::Rejected,
            $normalizedType === 'MC' => ElectronicInvoiceTransmissionStatus::DeliveryFailed,
            $normalizedType === 'AT' => ElectronicInvoiceTransmissionStatus::Undeliverable,
            $normalizedType === 'RC' => ElectronicInvoiceTransmissionStatus::Delivered,
            $normalizedType === 'NE' && $normalizedResult === 'EC01' => ElectronicInvoiceTransmissionStatus::Accepted,
            $normalizedType === 'NE' && $normalizedResult === 'EC02' => ElectronicInvoiceTransmissionStatus::Refused,
            $normalizedType === 'DT' => ElectronicInvoiceTransmissionStatus::TermsExpired,
            default => null,
        };
    }

    private function record(
        ElectronicInvoiceTransmission $transmission,
        ?ElectronicInvoiceTransmissionStatus $status,
        string $source,
        string $type,
        string $providerStatus,
        ?string $providerFilename,
        ?string $sdiId,
        ?string $providerInvoiceId,
        ?string $errorMessage,
        array $payload,
        ?string $document,
        CarbonImmutable $occurredAt,
    ): ElectronicInvoiceTransmission {
        $safePayload = $this->safePayload($payload);
        $documentHash = $document !== null ? hash('sha256', $document) : null;
        $eventKey = $this->eventKey(
            $transmission->getKey(),
            $type,
            $providerFilename,
            $providerStatus,
            $sdiId,
            $documentHash,
        );

        [$updated, $shouldNotify] = DB::transaction(function () use (
            $transmission,
            $status,
            $source,
            $type,
            $providerStatus,
            $providerFilename,
            $sdiId,
            $providerInvoiceId,
            $errorMessage,
            $safePayload,
            $document,
            $documentHash,
            $occurredAt,
            $eventKey,
        ): array {
            $locked = ElectronicInvoiceTransmission::query()
                ->whereKey($transmission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $event = ElectronicInvoiceEvent::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'electronic_invoice_transmission_id' => $locked->getKey(),
                    'source' => $source,
                    'type' => $type,
                    'status' => $providerStatus !== '' ? $providerStatus : null,
                    'provider_filename' => $providerFilename,
                    'sdi_id' => $sdiId,
                    'payload' => $safePayload,
                    'document_content' => $document,
                    'document_hash' => $documentHash,
                    'occurred_at' => $occurredAt,
                ],
            );

            $previousStatus = $locked->status;
            $statusChanged = $status !== null
                && $this->shouldAdvance($previousStatus, $status);
            $effectiveStatus = $statusChanged ? $status : $previousStatus;

            $updates = [
                'provider_status' => $providerStatus !== ''
                    ? $providerStatus
                    : $locked->provider_status,
                'response_payload' => $safePayload,
            ];

            if ($providerFilename !== null && $type === 'status') {
                $updates['upload_filename'] = $providerFilename;
            }

            if ($sdiId !== null) {
                $updates['sdi_id'] = $sdiId;
            }

            if ($providerInvoiceId !== null) {
                $updates['provider_invoice_id'] = $providerInvoiceId;
            }

            if ($locked->last_status_at === null || $occurredAt->greaterThan($locked->last_status_at)) {
                $updates['last_status_at'] = $occurredAt;
            }

            if ($statusChanged) {
                $updates['status'] = $effectiveStatus;
                $updates['error_message'] = $errorMessage;
                $updates['completed_at'] = $effectiveStatus->isTerminal()
                    ? ($locked->completed_at ?? $occurredAt)
                    : null;
            } elseif ($errorMessage !== null && blank($locked->error_message)) {
                $updates['error_message'] = $errorMessage;
            }

            $locked->update($updates);

            if ($locked->mode === 'live' && $statusChanged) {
                $invoice = $locked->invoice()->lockForUpdate()->firstOrFail();
                $fiscalStatus = $this->invoiceStatus($effectiveStatus);

                if ($fiscalStatus !== null) {
                    $invoice->update(['fiscal_status' => $fiscalStatus]);
                }
            }

            $notify = $event->wasRecentlyCreated
                && $statusChanged
                && $this->shouldNotify($effectiveStatus);

            return [$locked->fresh(['invoice.client']), $notify];
        });

        if ($shouldNotify) {
            $this->notifyFinanceUsers($updated, $errorMessage);
        }

        return $updated;
    }

    private function shouldAdvance(
        ElectronicInvoiceTransmissionStatus $current,
        ElectronicInvoiceTransmissionStatus $next,
    ): bool {
        if ($current === $next || $current === ElectronicInvoiceTransmissionStatus::Validated) {
            return false;
        }

        if ($current === ElectronicInvoiceTransmissionStatus::Delivered) {
            return in_array($next, [
                ElectronicInvoiceTransmissionStatus::Accepted,
                ElectronicInvoiceTransmissionStatus::Refused,
                ElectronicInvoiceTransmissionStatus::TermsExpired,
            ], true);
        }

        if ($current->isTerminal()) {
            return false;
        }

        $rank = [
            ElectronicInvoiceTransmissionStatus::Processing->value => 0,
            ElectronicInvoiceTransmissionStatus::Uncertain->value => 1,
            ElectronicInvoiceTransmissionStatus::TakenCharge->value => 2,
            ElectronicInvoiceTransmissionStatus::Sent->value => 3,
            ElectronicInvoiceTransmissionStatus::Delivered->value => 4,
            ElectronicInvoiceTransmissionStatus::DeliveryFailed->value => 4,
            ElectronicInvoiceTransmissionStatus::Undeliverable->value => 4,
            ElectronicInvoiceTransmissionStatus::Rejected->value => 4,
            ElectronicInvoiceTransmissionStatus::Accepted->value => 5,
            ElectronicInvoiceTransmissionStatus::Refused->value => 5,
            ElectronicInvoiceTransmissionStatus::TermsExpired->value => 5,
            ElectronicInvoiceTransmissionStatus::ProcessingError->value => 5,
            ElectronicInvoiceTransmissionStatus::Failed->value => 5,
        ];

        return ($rank[$next->value] ?? 0) >= ($rank[$current->value] ?? 0);
    }

    private function invoiceStatus(
        ElectronicInvoiceTransmissionStatus $status,
    ): ?InvoiceFiscalStatus {
        return match ($status) {
            ElectronicInvoiceTransmissionStatus::Processing,
            ElectronicInvoiceTransmissionStatus::Uncertain => InvoiceFiscalStatus::Transmitting,
            ElectronicInvoiceTransmissionStatus::TakenCharge,
            ElectronicInvoiceTransmissionStatus::Sent => InvoiceFiscalStatus::Sent,
            ElectronicInvoiceTransmissionStatus::Delivered => InvoiceFiscalStatus::Delivered,
            ElectronicInvoiceTransmissionStatus::DeliveryFailed => InvoiceFiscalStatus::DeliveryFailed,
            ElectronicInvoiceTransmissionStatus::Undeliverable => InvoiceFiscalStatus::Undeliverable,
            ElectronicInvoiceTransmissionStatus::Rejected => InvoiceFiscalStatus::Rejected,
            ElectronicInvoiceTransmissionStatus::Accepted => InvoiceFiscalStatus::Accepted,
            ElectronicInvoiceTransmissionStatus::Refused => InvoiceFiscalStatus::Refused,
            ElectronicInvoiceTransmissionStatus::TermsExpired => InvoiceFiscalStatus::TermsExpired,
            ElectronicInvoiceTransmissionStatus::ProcessingError => InvoiceFiscalStatus::ProcessingError,
            ElectronicInvoiceTransmissionStatus::Validated,
            ElectronicInvoiceTransmissionStatus::Failed => null,
        };
    }

    private function shouldNotify(ElectronicInvoiceTransmissionStatus $status): bool
    {
        return in_array($status, [
            ElectronicInvoiceTransmissionStatus::Delivered,
            ElectronicInvoiceTransmissionStatus::DeliveryFailed,
            ElectronicInvoiceTransmissionStatus::Undeliverable,
            ElectronicInvoiceTransmissionStatus::Rejected,
            ElectronicInvoiceTransmissionStatus::Accepted,
            ElectronicInvoiceTransmissionStatus::Refused,
            ElectronicInvoiceTransmissionStatus::TermsExpired,
            ElectronicInvoiceTransmissionStatus::ProcessingError,
        ], true);
    }

    private function notifyFinanceUsers(
        ElectronicInvoiceTransmission $transmission,
        ?string $detail,
    ): void {
        $users = User::query()
            ->where('status', 'active')
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Administration->value,
            ])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users,
            new ElectronicInvoiceStatusNotification(
                $transmission->invoice,
                $transmission->status,
                $detail,
            ),
        );
    }

    private function decodeDocument(mixed $encoded): ?string
    {
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $document = base64_decode($encoded, true);

        return is_string($document) && strlen($document) <= 5 * 1024 * 1024
            ? $document
            : null;
    }

    private function notificationResultFromXml(?string $document): string
    {
        if ($document === null) {
            return '';
        }

        return preg_match(
            '/<(?:[A-Za-z0-9_-]+:)?Esito>\s*(EC0[12])\s*<\//i',
            $document,
            $matches,
        ) === 1
            ? strtoupper($matches[1])
            : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $binaryFields = [
            'file',
            'pdffile',
            'datafile',
            'notifyxmlbase64',
            'invoicexmlbase64',
            'metadataxmlbase64',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $binaryFields, true)) {
                $payload[$key] = '[contenuto archiviato separatamente]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->safePayload($value);
            } elseif (is_string($value)) {
                $payload[$key] = Str::limit(strip_tags($value), 2000, '');
            }
        }

        return $payload;
    }

    private function eventKey(
        int|string $transmissionId,
        string $type,
        ?string $providerFilename,
        string $providerStatus,
        ?string $sdiId,
        ?string $documentHash,
    ): string {
        return hash('sha256', implode('|', [
            (string) $transmissionId,
            $type,
            $providerFilename ?? '',
            $providerStatus,
            $sdiId ?? '',
            $documentHash ?? '',
        ]));
    }

    private function date(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? Str::limit(trim((string) $value), 255, '')
            : null;
    }
}
