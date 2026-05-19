<?php

namespace App\Domain\Social\Actions;

use App\Models\AgencySocialAsset;
use App\Models\ClientSocialAccount;

class ValidateAgencyAssetAssignmentAction
{
    /**
     * Valida se un asset può essere assegnato a un cliente.
     */
    public function execute(AgencySocialAsset $asset, int $clientId, string $platform): \App\Domain\Social\DTOs\AssignmentValidationResult
    {
        // Controlla se l'asset è attivo e assegnabile
        if (!$asset->is_active) {
            return \App\Domain\Social\DTOs\AssignmentValidationResult::blocked("L'asset risulta disattivato o revocato e non può essere assegnato.");
        }
        
        if (!$asset->is_assignable) {
            return \App\Domain\Social\DTOs\AssignmentValidationResult::blocked("Questo asset Meta non possiede le autorizzazioni necessarie (is_assignable = false) per la gestione delegata.");
        }

        if ($asset->publishing_status !== \App\Enums\Social\PublishingStatus::Ready) {
            return \App\Domain\Social\DTOs\AssignmentValidationResult::warning("Questo asset è assegnabile, ma il suo stato di pubblicazione non risulta pronto (" . ($asset->publishing_status?->label() ?? 'Sconosciuto') . "). Potresti avere problemi a pubblicare post.");
        }

        // Controlla se la piattaforma corrisponde al tipo di asset (opzionale ma consigliato)
        if ($platform === 'facebook' && $asset->asset_type->value !== 'facebook_page') {
            return \App\Domain\Social\DTOs\AssignmentValidationResult::blocked("L'asset selezionato non è una pagina Facebook.");
        }
        if ($platform === 'instagram' && $asset->asset_type->value !== 'instagram_business_account') {
            return \App\Domain\Social\DTOs\AssignmentValidationResult::blocked("L'asset selezionato non è un account Instagram Business.");
        }

        // Controllo se l'asset è già stato assegnato a un altro cliente (solo per gestioni oauth)
        $existingAssignment = ClientSocialAccount::where('agency_social_asset_id', $asset->id)
            ->where('client_id', '!=', $clientId)
            ->where('connection_strategy', \App\Enums\Social\SocialConnectionStrategy::AgencyOauth)
            ->first();

        if ($existingAssignment) {
            $clientName = $existingAssignment->client->name ?? 'Un altro cliente';
            return \App\Domain\Social\DTOs\AssignmentValidationResult::warning("Attenzione: Questo asset è già stato assegnato a '{$clientName}'. Procedendo, lo stesso asset verrà usato per entrambi i clienti (scenario Multi-brand). Assicurati che sia il comportamento desiderato.");
        }

        return \App\Domain\Social\DTOs\AssignmentValidationResult::allowed();
    }
}
