<?php

namespace App\Console\Commands;

use App\Domain\Finance\Actions\CorrectBiessediliziaSettlement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CorrectBiessediliziaSettlementCommand extends Command
{
    protected $signature = 'finance:correct-biessedilizia-settlement {--actor= : ID dell’utente amministrativo} {--apply : Applica la correzione dopo le verifiche}';
    protected $description = 'Verifica o corregge esclusivamente il pagamento artificiale 18 della fattura 153, usando la nota 1.';

    public function handle(CorrectBiessediliziaSettlement $correction): int
    {
        $actor = ctype_digit((string) $this->option('actor')) ? User::find($this->option('actor')) : null;
        if (! $actor || $actor->status !== 'active' || ! $actor->canAccessFinance()) {
            $this->error('Indica con --actor un utente attivo autorizzato alla gestione finanziaria.');
            return self::FAILURE;
        }
        Auth::setUser($actor);
        try {
            $result = $correction->execute((bool) $this->option('apply'));
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));
            return self::FAILURE;
        } finally {
            Auth::forgetUser();
        }
    }
}
