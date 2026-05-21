<?php

namespace App\Domain\Social\Actions;

use App\Models\AgencySocialAsset;
use Illuminate\Support\Facades\Log;

class ResolveAssetAccessTokenAction
{
    /**
     * Risolve il token da utilizzare per la pubblicazione o per le chiamate API
     * su un determinato asset.
     * Implementa la logica anti-cycle e di fall-back sul parent.
     */
    public function execute(AgencySocialAsset $asset, array $visitedIds = []): ?string
    {
        // Verifica se la connessione alla base richiede re-auth
        if ($asset->connection && $asset->connection->requires_reauth) {
            throw new \App\Exceptions\Social\TokenRevokedException("La connessione dell'asset (Connection ID: {$asset->connection->id}) richiede riautenticazione. Impossibile risolvere il token.");
        }

        // Anti-cycle safety net (rileva loop esatto)
        if (in_array($asset->id, $visitedIds)) {
            Log::error("ResolveAssetAccessTokenAction: Ciclo rilevato per l'asset ID {$asset->id}");
            return null;
        }

        $visitedIds[] = $asset->id;

        // Se l'asset ha un suo token diretto, lo usiamo
        if (!empty($asset->page_access_token)) {
            return $asset->page_access_token;
        }

        // Se non ha il token ma ha un parent, chiediamo al parent
        if ($asset->parent_asset_id) {
            $parent = $asset->parentAsset; // Relazione Eloquent
            if ($parent) {
                return $this->execute($parent, $visitedIds);
            }
        }

        // Se arriviamo qui e siamo un root asset (senza parent) e non abbiamo token,
        // non facciamo fallback cieco al connection access_token (pericoloso e non deterministico).
        // Il publishing richiede un page token reale.
        return null;
    }
}
