<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SocialCircuitBreaker
{
    public const STATE_CLOSED = 'closed';     // Funzionamento normale
    public const STATE_OPEN = 'open';         // Troppi errori, richieste bloccate
    public const STATE_HALF_OPEN = 'half_open'; // Periodo di test dopo blocco

    protected string $provider;
    protected int $failureThreshold;
    protected int $resetTimeout; // secondi

    public function __construct(string $provider = 'meta', int $failureThreshold = 10, int $resetTimeout = 300)
    {
        $this->provider = $provider;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
    }

    protected function getStateKey(): string
    {
        return "circuit_breaker:{$this->provider}:state";
    }

    protected function getFailureCountKey(): string
    {
        return "circuit_breaker:{$this->provider}:failures";
    }

    public function getState(): string
    {
        $state = Cache::get($this->getStateKey());
        
        if ($state === self::STATE_OPEN) {
            return self::STATE_OPEN;
        }
        
        if ($state === self::STATE_HALF_OPEN) {
            return self::STATE_HALF_OPEN;
        }

        // Se lo stato in cache non è OPEN, ma abbiamo raggiunto la soglia di fallimenti,
        // significa che il TTL del blocco OPEN è scaduto, quindi siamo in HALF_OPEN.
        $count = (int) Cache::get($this->getFailureCountKey(), 0);
        if ($count >= $this->failureThreshold) {
            // Aggiorniamo esplicitamente lo stato a half_open per questa finestra
            Cache::put($this->getStateKey(), self::STATE_HALF_OPEN);
            return self::STATE_HALF_OPEN;
        }

        return self::STATE_CLOSED;
    }

    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Permettiamo 1 chiamata per testare se è tornato online
            // Il lock copre il tempo di una richiesta API lenta per evitare falsi storm
            return Cache::add("circuit_breaker:{$this->provider}:probe_lock", true, 30);
        }

        return false;
    }

    public function recordSuccess(): void
    {
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getStateKey());
    }

    public function recordFailure(): void
    {
        $count = Cache::increment($this->getFailureCountKey());

        // Se abbiamo raggiunto/superato la soglia O eravamo in half-open, 
        // riapriamo il circuito resettando il timeout.
        if ($count >= $this->failureThreshold) {
            Cache::put($this->getStateKey(), self::STATE_OPEN, $this->resetTimeout);
        }
    }
}
