<?php

namespace App\Domain\Social\DTOs;

use App\Enums\Social\SocialPlatform;

final readonly class MarketingCampaignPostPublicationSnapshot implements \JsonSerializable
{
    public function __construct(
        public int $post_id,
        public int $version_id,
        public int $version_number,
        public string $provider,
        public SocialPlatform $platform,
        public int $social_account_id,
        public string $account_external_id,
        public ?string $page_id,
        public ?string $profile_id,
        public array $privacy_options,
        public string $publication_type,
        public string $content_type,
        public string $title,
        public string $caption,
        public array $hashtags,
        public array $media, // Structured array without ephemeral URLs
        public ?string $scheduled_date,
        public ?string $scheduled_time,
        public array $platform_options,
        public int $schema_version
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'post_id' => $this->post_id,
            'version_id' => $this->version_id,
            'version_number' => $this->version_number,
            'provider' => $this->provider,
            'platform' => $this->platform->value,
            'target' => [
                'social_account_id' => $this->social_account_id,
                'external_id' => $this->account_external_id,
                'page_id' => $this->page_id,
                'profile_id' => $this->profile_id,
                'privacy_options' => $this->privacy_options,
                'publication_type' => $this->publication_type,
            ],
            'content_type' => $this->content_type,
            'title' => $this->title,
            'caption' => $this->caption,
            'hashtags' => $this->hashtags,
            'media' => $this->media,
            'scheduled_date' => $this->scheduled_date,
            'scheduled_time' => $this->scheduled_time,
            'platform_options' => $this->platform_options,
            'schema_version' => $this->schema_version,
        ];
    }
}
