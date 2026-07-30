<?php

namespace App\Support\Network;

class PublicNetworkAddressValidator
{
    /**
     * Valida se un indirizzo IP è considerato pubblico e sicuro da chiamare per prevenire SSRF.
     * Blocca IPv4/IPv6 privati, loopback, link-local, multicast, e riservati (es. cloud metadata).
     */
    public function isValid(string $ip): bool
    {
        // filter_var con flag blocca già la maggior parte degli indirizzi privati (10., 192.168., 172.16.)
        // e riservati (127., 169.254., multicast, ecc).
        $isValid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isValid === false) {
            return false;
        }

        // Blocca esplicitamente eventuali IP noti per i metadata service cloud (es. AWS, GCP)
        // filter_var blocca 169.254.0.0/16 grazie a FILTER_FLAG_NO_RES_RANGE, ma aggiungiamo check di sicurezza.
        $blockedPrefixes = [
            '169.254.', // Cloud metadata (AWS, GCP, Azure)
            '0.',       // "This host on this network"
            '192.0.0.', // IETF Protocol Assignments
            '198.18.',  // Network Interconnect Device Benchmark Testing
            '198.19.',
            '192.0.2.', // TEST-NET-1
            '198.51.100.', // TEST-NET-2
            '203.0.113.',  // TEST-NET-3
            '192.88.99.', // 6to4 Relay Anycast
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return false;
            }
        }

        // Carrier-grade NAT is 100.64.0.0/10, not the entire public 100.0.0.0/8 range.
        if ($this->isIpv4InCidr($ip, '100.64.0.0', 10)) {
            return false;
        }

        // IPv6 extra checks: Unique-Local (fc00::/7) non coperto perfettamente da vecchie versioni PHP
        if (str_starts_with(strtolower($ip), 'fc') || str_starts_with(strtolower($ip), 'fd')) {
            return false;
        }

        return true;
    }

    private function isIpv4InCidr(string $ip, string $network, int $prefixLength): bool
    {
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
        ) {
            return false;
        }

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $mask = -1 << (32 - $prefixLength);

        return ($ipLong & $mask) === ($networkLong & $mask);
    }
}
