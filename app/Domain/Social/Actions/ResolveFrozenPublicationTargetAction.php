<?php

namespace App\Domain\Social\Actions;

use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use InvalidArgumentException;

class ResolveFrozenPublicationTargetAction
{
    /**
     * Resolves the deterministic target values to be frozen in the snapshot.
     * This avoids runtime live lookups during publication.
     */
    public function execute(SocialPlatform $platform, ClientSocialAccount $account): array
    {
        $usesAgencyAsset = $account->connection_strategy?->value === 'agency_oauth';
        $asset = $usesAgencyAsset ? $account->agencyAsset : null;

        $target = [
            'social_account_id' => $account->id,
            'external_id' => null,
            'page_id' => null,
            'profile_id' => null,
        ];

        if ($platform === SocialPlatform::Facebook) {
            $externalId = $asset
                ? ($asset->facebook_page_id ?: $asset->provider_asset_id)
                : ($account->facebook_page_id ?: $account->provider_account_id);

            if (blank($externalId)) {
                throw new InvalidArgumentException('Impossibile determinare la pagina Facebook dal conto social congelato.');
            }

            $target['page_id'] = (string) $externalId;
            $target['external_id'] = (string) $externalId;
        } elseif ($platform === SocialPlatform::Instagram) {
            $externalId = $asset
                ? ($asset->instagram_business_account_id ?: $asset->provider_asset_id)
                : ($account->instagram_business_account_id ?: $account->provider_account_id);

            if (blank($externalId)) {
                throw new InvalidArgumentException('Impossibile determinare il profilo Instagram dal conto social congelato.');
            }

            $target['external_id'] = (string) $externalId;
            $target['profile_id'] = (string) $externalId;
        } else {
            $externalId = $account->provider_account_id
                ?: $account->tiktok_open_id
                ?: $account->tiktok_account_id;

            if (blank($externalId)) {
                throw new InvalidArgumentException('Impossibile determinare il profilo TikTok dal conto social congelato.');
            }

            $target['external_id'] = (string) $externalId;
        }

        return $target;
    }
}
