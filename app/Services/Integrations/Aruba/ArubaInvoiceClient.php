<?php

namespace App\Services\Integrations\Aruba;

use App\Domain\Finance\DTOs\FatturaPaXmlDocument;
use App\Exceptions\Finance\ArubaApiException;
use App\Models\IntegrationLog;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class ArubaInvoiceClient
{
    public function __construct(
        private readonly ArubaConfiguration $configuration,
        private readonly ArubaAuthenticator $authenticator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function upload(FatturaPaXmlDocument $xml, bool $dryRun): array
    {
        $this->configuration->assertCanUpload($dryRun);
        $endpoint = $this->configuration->apiBaseUrl().'/services/invoice/upload';
        $log = $this->startLog(
            endpoint: $endpoint,
            event: $dryRun ? 'electronic_invoice_dry_run' : 'electronic_invoice_upload',
            payload: [
                'dry_run' => $dryRun,
                'xml_hash' => $xml->hash,
                'xml_bytes' => strlen($xml->content),
                'filename' => $xml->filename,
            ],
        );

        $payload = [
            'dataFile' => base64_encode($xml->content),
            'senderPIVA' => '',
            'skipExtraSchema' => false,
            'dryRun' => $dryRun,
        ];

        if ($this->configuration->signatureDomain() !== '') {
            $payload['domain'] = $this->configuration->signatureDomain();
        }

        if ($this->configuration->signatureCredential() !== '') {
            $payload['credential'] = $this->configuration->signatureCredential();
        }

        try {
            $response = $this->request()->post($endpoint, $payload);
            $safePayload = ProviderErrorSanitizer::payload($response);
            $log->update([
                'status_code' => $response->status(),
                'response' => $safePayload,
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                if ($response->status() === 401) {
                    $this->authenticator->forget();
                }

                throw $this->httpException($response, ! $dryRun);
            }

            $result = $response->json();

            if (! is_array($result)) {
                throw new ArubaApiException(
                    'Aruba ha restituito una risposta non leggibile.',
                    uncertain: ! $dryRun,
                    httpStatus: $response->status(),
                );
            }

            $errorCode = trim((string) ($result['errorCode'] ?? ''));
            $uploadFilename = trim((string) ($result['uploadFileName'] ?? ''));

            if (! in_array($errorCode, ['', '0000'], true) || $uploadFilename === '') {
                throw $this->providerException($result, ! $dryRun);
            }

            return [
                'error_code' => $errorCode !== '' ? $errorCode : '0000',
                'error_description' => (string) ($result['errorDescription'] ?? ''),
                'request_identifier' => $this->requestIdentifier(
                    (string) ($result['errorDescription'] ?? '')
                ),
                'upload_filename' => $uploadFilename,
                'response_payload' => $result,
            ];
        } catch (ArubaApiException $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->userMessage,
                'processed_at' => now(),
            ]);

            throw $exception;
        } catch (ConnectionException $exception) {
            $message = $dryRun
                ? 'La verifica con Aruba non ha ricevuto risposta. Puoi riprovarla senza rischi.'
                : 'Non è arrivata una risposta certa da Aruba. Non ripetere l’invio finché lo stato non viene verificato.';
            $log->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException(
                $message,
                uncertain: ! $dryRun,
                previous: $exception,
            );
        } catch (Throwable $exception) {
            $safe = ProviderErrorSanitizer::safeText($exception->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $safe,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException(
                'L’operazione Aruba non è stata completata.',
                uncertain: ! $dryRun,
                previous: $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function userInfo(): array
    {
        $this->configuration->assertCanConnect();
        $endpoint = $this->configuration->authBaseUrl().'/auth/userInfo';

        return $this->getJson($endpoint, 'electronic_invoice_user_info');
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceDetail(string $filename): array
    {
        $this->configuration->assertCanConnect();
        $endpoint = $this->configuration->apiBaseUrl().'/api/v2/invoices-out/detail';

        return $this->getJson($endpoint, 'electronic_invoice_detail', [
            'filename' => $filename,
            'includeFile' => 'false',
            'includePdf' => 'false',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function notifications(string $filename): array
    {
        $this->configuration->assertCanConnect();
        $endpoint = $this->configuration->apiBaseUrl().'/api/v2/invoices-out/notifications';

        return $this->getJson($endpoint, 'electronic_invoice_notifications', [
            'filename' => $filename,
        ]);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->authenticator->token())
            ->connectTimeout($this->configuration->connectTimeout())
            ->timeout($this->configuration->timeout())
            ->withOptions(['allow_redirects' => false]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $endpoint, string $event, array $query = []): array
    {
        $log = $this->startLog($endpoint, $event, $query);

        try {
            $response = $this->request()->get($endpoint, $query);
            $safePayload = ProviderErrorSanitizer::payload($response);
            $log->update([
                'status_code' => $response->status(),
                'response' => $safePayload,
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                if ($response->status() === 401) {
                    $this->authenticator->forget();
                }

                throw $this->httpException($response, false);
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new ArubaApiException('Aruba ha restituito una risposta non leggibile.');
            }

            return $payload;
        } catch (ArubaApiException $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->userMessage,
                'processed_at' => now(),
            ]);

            throw $exception;
        } catch (ConnectionException $exception) {
            $message = 'Aruba non è raggiungibile in questo momento.';
            $log->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException($message, previous: $exception);
        } catch (Throwable $exception) {
            $message = ProviderErrorSanitizer::safeText($exception->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException(
                'Non è stato possibile leggere l’aggiornamento da Aruba.',
                previous: $exception,
            );
        }
    }

    private function httpException(Response $response, bool $liveUpload): ArubaApiException
    {
        $uncertain = $liveUpload && $response->serverError();
        $message = match ($response->status()) {
            401, 403 => 'Aruba non ha autorizzato l’operazione. Controlla utenza, servizio Premium e deleghe.',
            413 => 'Il file della fattura supera il limite consentito da Aruba.',
            429 => 'È stato raggiunto il limite temporaneo di richieste Aruba. Attendi prima di riprovare.',
            default => $uncertain
                ? 'Aruba ha avuto un problema durante l’invio. Lo stato deve essere verificato prima di ripetere l’operazione.'
                : 'Aruba non ha accettato la richiesta.',
        };

        return new ArubaApiException(
            $message,
            uncertain: $uncertain,
            responsePayload: ProviderErrorSanitizer::payload($response),
            httpStatus: $response->status(),
        );
    }

    private function providerException(array $payload, bool $liveUpload): ArubaApiException
    {
        $code = trim((string) ($payload['errorCode'] ?? ''));
        $description = ProviderErrorSanitizer::safeText(
            (string) ($payload['errorDescription'] ?? '')
        );
        $uncertainCodes = ['0001', '0013', '0034'];
        $uncertain = $liveUpload && in_array($code, $uncertainCodes, true);

        $message = match ($code) {
            '0012' => 'Aruba non ha autorizzato l’operazione.',
            '0033' => 'Il file della fattura supera il limite consentito da Aruba.',
            '0034' => 'Aruba segnala che questo file è già stato ricevuto. Lo stato verrà verificato senza ripetere l’invio.',
            '0092', '0096' => 'Aruba ha trovato dati non validi nel file della fattura.',
            '0093' => 'La delega Aruba necessaria per l’invio non è valida.',
            '0094' => 'Aruba non riconosce i dati del soggetto trasmittente nel file.',
            '0095' => 'Il servizio Aruba è temporaneamente indisponibile.',
            '0097' => 'Lo spazio di conservazione Aruba non è sufficiente.',
            '0098' => 'Aruba non ha riconosciuto il contenuto del file.',
            default => $uncertain
                ? 'Aruba non ha restituito un esito certo. Non ripetere l’invio finché lo stato non viene verificato.'
                : 'Aruba non ha accettato la fattura.',
        };

        if ($description !== '') {
            $message .= ' Dettaglio: '.$description;
        }

        return new ArubaApiException(
            $message,
            providerCode: $code !== '' ? $code : null,
            uncertain: $uncertain,
            responsePayload: $payload,
            httpStatus: 200,
        );
    }

    private function requestIdentifier(string $description): ?string
    {
        return preg_match('/[-–]\s*([A-Za-z0-9]{10,})\s*$/', $description, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startLog(
        string $endpoint,
        string $event,
        array $payload,
    ): IntegrationLog {
        return IntegrationLog::create([
            'provider' => 'aruba',
            'direction' => 'outbound',
            'endpoint' => $endpoint,
            'event' => $event,
            'payload' => $payload,
            'status' => 'processing',
        ]);
    }
}
