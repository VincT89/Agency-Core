<?php

namespace App\Domain\Social\TikTok\Strategies;

class PullFromUrlStrategy implements TikTokMediaTransferStrategy
{
    public function applyStrategy(string $accessToken, array $basePayload, array $mediaUrls, string $postType): array
    {
        $payload = $basePayload;

        if ($postType === 'video') {
            $payload['source_info'] = [
                'source' => 'PULL_FROM_URL',
                'video_url' => $mediaUrls[0],
            ];

            return $payload;
        }

        if ($postType === 'photo') {
            $payload['source_info'] = [
                'source' => 'PULL_FROM_URL',
                'photo_images' => array_values($mediaUrls),
            ];

            return $payload;
        }

        throw new \InvalidArgumentException("Post type TikTok non supportato: {$postType}");
    }
}
