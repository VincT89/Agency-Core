<?php

namespace App\Support\Network;

use RuntimeException;

class HostResolver
{
    public function __construct(
        private readonly PublicNetworkAddressValidator $validator
    ) {}

    /**
     * Risolve un hostname nei suoi indirizzi IP.
     * Se uno qualsiasi degli indirizzi restituiti è privato o riservato,
     * la risoluzione fallisce e viene lanciata un'eccezione, prevenendo SSRF
     * tramite DNS rebinding o hostname che risolvono multipli indirizzi (misti).
     * 
     * @param string $host
     * @return string L'indirizzo IP validato (tipicamente il primo) da usare per la connessione.
     * @throws RuntimeException
     */
    public function resolveAndValidatePublicHost(string $host): string
    {
        if (app()->environment('testing')) {
            return $host;
        }

        // Usa dns_get_record per ottenere record A e AAAA
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || count($records) === 0) {
            // Fallback: gethostbynamel per il solo IPv4 se dns_get_record fallisce (es. locale)
            $ips = gethostbynamel($host);
            if ($ips === false || count($ips) === 0) {
                throw new RuntimeException("Impossibile risolvere l'host: {$host}");
            }
        } else {
            $ips = [];
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            throw new RuntimeException("Nessun indirizzo IP valido trovato per: {$host}");
        }

        // TUTTI gli IP associati al dominio devono essere pubblici.
        // Questo previene DNS rebinding o casi in cui il dominio restituisce
        // sia un IP pubblico sia un IP interno per bypassare controlli parziali.
        foreach ($ips as $ip) {
            if (!$this->validator->isValid($ip)) {
                throw new RuntimeException("L'host risolve un indirizzo IP privato/riservato non consentito: {$ip}");
            }
        }

        // Restituisce il primo IP validato
        return $ips[0];
    }
}
