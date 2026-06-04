<?php

namespace App\Domain\Social\TikTok\Strategies;

interface TikTokMediaTransferStrategy
{
    /**
     * Applica la strategia di trasferimento media al payload per le API di TikTok.
     * Restituisce un array con il payload aggiornato e le informazioni sulla strategy.
     *
     * @param string $accessToken
     * @param array $basePayload Payload di base (es. title, privacy_level_info)
     * @param array $mediaUrls Array di URL media (video o photo)
     * @param string $postType 'video' o 'photo'
     * @return array
     */
    public function applyStrategy(string $accessToken, array $basePayload, array $mediaUrls, string $postType): array;
}
