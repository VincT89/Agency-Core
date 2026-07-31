<?php

namespace App\Http\Controllers\Api\V1\Integrations\Aruba;

use App\Domain\Finance\Services\ElectronicInvoiceStatusUpdater;
use App\Http\Controllers\Controller;
use App\Models\ElectronicInvoiceTransmission;
use App\Models\IntegrationLog;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ArubaCallbackController extends Controller
{
    public function __construct(
        private readonly ArubaConfiguration $configuration,
        private readonly ElectronicInvoiceStatusUpdater $statusUpdater,
    ) {}

    public function updateInvoiceStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255'],
            'countryCode' => ['required', 'string', 'size:2'],
            'vatCode' => ['required', 'string', 'max:28'],
            'fiscalCode' => ['present', 'nullable', 'string', 'max:28'],
            'invoiceFileName' => ['required', 'string', 'max:255'],
            'sdiIdentification' => ['present', 'nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:100'],
            'errorDescription' => ['nullable', 'string', 'max:2000'],
            'updateDate' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Parametri della notifica di stato non validi.',
                'errors' => $validator->errors(),
            ], 400);
        }

        $payload = $validator->validated();
        $log = $this->startLog('update_invoice_status', $payload);

        try {
            $transmission = $this->findByFilename($payload['invoiceFileName']);

            if ($transmission === null) {
                $this->completeLog($log, false);

                return response()->json(['ok' => true]);
            }

            $this->statusUpdater->applyStatusCallback($transmission, $payload);
            $this->completeLog($log, true);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            $this->failLog($log, $exception);

            return response()->json([
                'ok' => false,
                'message' => 'Aggiornamento temporaneamente non disponibile.',
            ], 500);
        }
    }

    public function createNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255'],
            'countryCode' => ['required', 'string', 'size:2'],
            'vatCode' => ['required', 'string', 'max:28'],
            'fiscalCode' => ['present', 'nullable', 'string', 'max:28'],
            'inOut' => ['required', 'string', 'in:OUT,IN'],
            'notifyType' => ['required', 'string', 'in:NS,MC,AT,RC,NE,NE_EC01,NE_EC02,DT'],
            'sdiIdentification' => ['required', 'string', 'max:100'],
            'notifyFileName' => ['required', 'string', 'max:255'],
            'notifyXmlBase64' => ['required', 'string', 'max:7000000'],
            'notificationDate' => ['nullable', 'date'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            $encoded = $request->input('notifyXmlBase64');
            $document = is_string($encoded) ? base64_decode($encoded, true) : false;

            if (
                ! is_string($document)
                || $document === ''
                || strlen($document) > 5 * 1024 * 1024
                || str_contains(strtoupper($document), '<!DOCTYPE')
            ) {
                $validator->errors()->add(
                    'notifyXmlBase64',
                    'Il documento della notifica non è valido.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Parametri della notifica SdI non validi.',
                'errors' => $validator->errors(),
            ], 400);
        }

        $payload = $validator->validated();
        $log = $this->startLog('create_notification', $payload);

        try {
            if ($payload['inOut'] === 'IN') {
                $this->completeLog($log, false);

                return response()->json(['ok' => true]);
            }

            $transmission = ElectronicInvoiceTransmission::query()
                ->where('provider', 'aruba')
                ->where('environment', $this->configuration->environment())
                ->where('mode', 'live')
                ->where('sdi_id', $payload['sdiIdentification'])
                ->latest('id')
                ->first();

            if ($transmission === null) {
                $this->completeLog($log, false);

                return response()->json(['ok' => true]);
            }

            $this->statusUpdater->applyNotification(
                $transmission,
                $payload,
                'callback',
            );
            $this->completeLog($log, true);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            $this->failLog($log, $exception);

            return response()->json([
                'ok' => false,
                'message' => 'Notifica temporaneamente non disponibile.',
            ], 500);
        }
    }

    private function findByFilename(string $filename): ?ElectronicInvoiceTransmission
    {
        $unsigned = Str::endsWith(strtolower($filename), '.p7m')
            ? substr($filename, 0, -4)
            : $filename;

        return ElectronicInvoiceTransmission::query()
            ->where('provider', 'aruba')
            ->where('environment', $this->configuration->environment())
            ->where('mode', 'live')
            ->where(function ($query) use ($filename, $unsigned): void {
                $query->whereIn('upload_filename', [$filename, $unsigned])
                    ->orWhereIn('xml_filename', [$filename, $unsigned]);
            })
            ->latest('id')
            ->first();
    }

    private function startLog(string $event, array $payload): IntegrationLog
    {
        $safePayload = $payload;

        if (isset($safePayload['notifyXmlBase64'])) {
            $document = base64_decode((string) $safePayload['notifyXmlBase64'], true);
            $safePayload['notifyXmlBase64'] = '[contenuto archiviato separatamente]';
            $safePayload['document_hash'] = is_string($document)
                ? hash('sha256', $document)
                : null;
        }

        return IntegrationLog::create([
            'provider' => 'aruba',
            'direction' => 'inbound',
            'endpoint' => request()->path(),
            'event' => $event,
            'payload' => $safePayload,
            'status' => 'processing',
        ]);
    }

    private function completeLog(IntegrationLog $log, bool $matched): void
    {
        $log->update([
            'response' => ['matched' => $matched],
            'status_code' => 200,
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    private function failLog(IntegrationLog $log, Throwable $exception): void
    {
        $log->update([
            'status_code' => 500,
            'status' => 'failed',
            'error_message' => ProviderErrorSanitizer::safeText($exception->getMessage()),
            'processed_at' => now(),
        ]);
    }
}
