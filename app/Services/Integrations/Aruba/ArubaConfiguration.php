<?php

namespace App\Services\Integrations\Aruba;

use App\Exceptions\Finance\ArubaConfigurationException;

class ArubaConfiguration
{
    public function environment(): string
    {
        $environment = strtolower((string) config(
            'services.aruba_einvoicing.environment',
            'demo'
        ));

        if (! in_array($environment, ['demo', 'production'], true)) {
            throw new ArubaConfigurationException(
                'L’ambiente Aruba configurato non è valido.'
            );
        }

        return $environment;
    }

    public function enabled(): bool
    {
        return (bool) config('services.aruba_einvoicing.enabled', false);
    }

    public function credentialsConfigured(): bool
    {
        return filled($this->username()) && filled($this->password());
    }

    public function callbackConfigured(): bool
    {
        return strlen($this->callbackKey()) >= 32;
    }

    public function allowSend(): bool
    {
        return (bool) config('services.aruba_einvoicing.allow_send', false);
    }

    public function requireDryRun(): bool
    {
        return (bool) config('services.aruba_einvoicing.require_dry_run', true);
    }

    public function username(): string
    {
        return trim((string) config('services.aruba_einvoicing.username', ''));
    }

    public function password(): string
    {
        return (string) config('services.aruba_einvoicing.password', '');
    }

    public function callbackKey(): string
    {
        return (string) config('services.aruba_einvoicing.callback_key', '');
    }

    public function signatureDomain(): string
    {
        return trim((string) config('services.aruba_einvoicing.signature_domain', ''));
    }

    public function signatureCredential(): string
    {
        return (string) config('services.aruba_einvoicing.signature_credential', '');
    }

    public function authBaseUrl(): string
    {
        return rtrim((string) config('services.aruba_einvoicing.auth_base_url'), '/');
    }

    public function apiBaseUrl(): string
    {
        return rtrim((string) config('services.aruba_einvoicing.api_base_url'), '/');
    }

    public function connectTimeout(): int
    {
        return max(1, (int) config('services.aruba_einvoicing.connect_timeout', 5));
    }

    public function timeout(): int
    {
        return max(1, (int) config('services.aruba_einvoicing.timeout', 20));
    }

    public function assertCanConnect(): void
    {
        $this->environment();

        if (! $this->enabled()) {
            throw new ArubaConfigurationException(
                'Il collegamento Aruba non è ancora attivo.'
            );
        }

        if (! $this->credentialsConfigured()) {
            throw new ArubaConfigurationException(
                'Le credenziali Aruba non sono ancora configurate.'
            );
        }
    }

    public function assertCanUpload(bool $dryRun): void
    {
        $this->assertCanConnect();

        if ($dryRun) {
            return;
        }

        if (! $this->allowSend()) {
            throw new ArubaConfigurationException(
                'L’invio delle fatture è bloccato dalla configurazione di sicurezza.'
            );
        }

        if ($this->environment() === 'production') {
            if (! $this->callbackConfigured()) {
                throw new ArubaConfigurationException(
                    'Configura una chiave sicura per le notifiche Aruba prima dell’invio reale.'
                );
            }

            if (! str_starts_with(strtolower((string) config('app.url')), 'https://')) {
                throw new ArubaConfigurationException(
                    'L’invio reale richiede che il gestionale sia raggiungibile tramite HTTPS.'
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $environment = $this->environment();

        return [
            'enabled' => $this->enabled(),
            'environment' => $environment,
            'environment_label' => $environment === 'production'
                ? 'Produzione'
                : 'Collaudo Aruba',
            'credentials_configured' => $this->credentialsConfigured(),
            'callback_configured' => $this->callbackConfigured(),
            'allow_send' => $this->allowSend(),
            'ready_for_validation' => $this->enabled() && $this->credentialsConfigured(),
            'ready_for_send' => $this->enabled()
                && $this->credentialsConfigured()
                && $this->allowSend()
                && (
                    $environment !== 'production'
                    || (
                        $this->callbackConfigured()
                        && str_starts_with(strtolower((string) config('app.url')), 'https://')
                    )
                ),
        ];
    }
}
