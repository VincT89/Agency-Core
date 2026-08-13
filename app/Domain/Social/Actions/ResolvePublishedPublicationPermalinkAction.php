<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Services\InstagramContainerStatusService;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;

class ResolvePublishedPublicationPermalinkAction
{
    public function __construct(
        private InstagramContainerStatusService $instagramService,
        private ResolveAssetAccessTokenAction $resolveAssetAccessToken,
        private TikTokContentPostingService $tiktokService,
    ) {}

    public function execute(MarketingCampaignPostPublication $publication): ?string
    {
        if ($publication->status !== PublicationStatus::Published) {
            return null;
        }

        $publication->loadMissing('socialAccount.agencyAsset.connection');

        if ($resolved = $publication->resolved_external_permalink) {
            return $this->persist($publication, $resolved);
        }

        return match ($publication->platform) {
            SocialPlatform::Instagram => $this->resolveInstagram($publication),
            SocialPlatform::Tiktok => $this->resolveTikTok($publication),
            default => null,
        };
    }

    private function resolveInstagram(
        MarketingCampaignPostPublication $publication
    ): ?string {
        $mediaId = trim((string) $publication->external_post_id);
        $accessToken = $this->instagramAccessToken($publication->socialAccount);

        if ($mediaId === '' || $accessToken === null) {
            return null;
        }

        $permalink = $this->instagramService->getMediaPermalink(
            $mediaId,
            $accessToken,
            $publication->correlation_id
        );

        return $permalink ? $this->persist($publication, $permalink) : null;
    }

    private function resolveTikTok(
        MarketingCampaignPostPublication $publication
    ): ?string {
        $account = $publication->socialAccount;
        if (! $account) {
            return null;
        }

        $accessToken = $account->access_token;
        $publishId = trim((string) (
            $publication->external_task_id ?: $publication->external_container_id
        ));

        if (blank($publication->external_post_id) && $accessToken && $publishId !== '') {
            $status = $this->tiktokService->getPostStatus($accessToken, $publishId);
            $publicPostId = $status->publicPostId();

            if ($publicPostId !== null) {
                $publication->update(['external_post_id' => $publicPostId]);
            }
        }

        if ($this->tiktokUsername($account) === null && $accessToken) {
            $creatorInfo = $this->tiktokService->queryCreatorInfo(
                $accessToken,
                (string) $account->id,
                true
            );
            $creatorUsername = $this->normaliseUsername(
                $creatorInfo['creator_username'] ?? null
            );

            if ($creatorUsername !== null) {
                $apiMetadata = $account->api_metadata ?? [];
                $apiMetadata['content_posting_info'] = $creatorInfo;

                $account->update([
                    'username' => $creatorUsername,
                    'api_metadata' => $apiMetadata,
                    'last_api_check_at' => now(),
                ]);
            }
        }

        $publication = $publication->fresh(['socialAccount']);
        $resolved = $publication?->resolved_external_permalink;

        return $resolved ? $this->persist($publication, $resolved) : null;
    }

    private function instagramAccessToken(?ClientSocialAccount $account): ?string
    {
        if (! $account) {
            return null;
        }

        if (
            $account->connection_strategy?->value === 'agency_oauth'
            && $account->agencyAsset
        ) {
            $token = $this->resolveAssetAccessToken->execute($account->agencyAsset);

            return filled($token) ? $token : null;
        }

        return filled($account->access_token) ? $account->access_token : null;
    }

    private function tiktokUsername(ClientSocialAccount $account): ?string
    {
        return $this->normaliseUsername($account->username)
            ?? $this->normaliseUsername(data_get(
                $account->api_metadata,
                'content_posting_info.creator_username'
            ));
    }

    private function normaliseUsername(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $username = ltrim(trim($value), '@');

        return $username !== '' ? $username : null;
    }

    private function persist(
        MarketingCampaignPostPublication $publication,
        string $permalink
    ): string {
        if ($publication->external_permalink !== $permalink) {
            $publication->update(['external_permalink' => $permalink]);
        }

        return $permalink;
    }
}
