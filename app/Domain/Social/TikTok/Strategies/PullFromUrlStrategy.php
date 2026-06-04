<?php

namespace App\Domain\Social\TikTok\Strategies;

class PullFromUrlStrategy implements TikTokMediaTransferStrategy
{
    public function applyStrategy(string $accessToken, array $basePayload, array $mediaUrls, string $postType): array
    {
        $payload = $basePayload;
        $payload['source_info'] = [
            'source' => 'PULL_FROM_URL',
        ];

        if ($postType === 'video') {
            $payload['source_info']['video_url'] = $mediaUrls[0];
        } elseif ($postType === 'photo') {
            $payload['source_info']['photo_images'] = array_map(function ($url) {
                return $url;
            }, $mediaUrls);
        }

        return $payload;
    }
}
