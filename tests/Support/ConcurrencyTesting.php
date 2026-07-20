<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

trait ConcurrencyTesting
{
    /**
     * Setup di una seconda connessione al database per simulare transazioni concorrenti.
     */
    protected function setupConcurrentConnection(): string
    {
        $default = config('database.default');
        $connectionConfig = config("database.connections.{$default}");
        
        $concurrentName = $default . '_concurrent';
        
        Config::set("database.connections.{$concurrentName}", $connectionConfig);
        
        return $concurrentName;
    }
    
    /**
     * Esegue una callback in una transazione concorrente separata.
     */
    protected function runConcurrently(string $connection, callable $callback)
    {
        return DB::connection($connection)->transaction($callback);
    }
}
