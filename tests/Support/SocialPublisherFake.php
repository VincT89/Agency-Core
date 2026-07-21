<?php

namespace Tests\Support;

use App\Domain\Social\Publishing\SocialPublisherInterface;
use App\Domain\Social\Publishing\PublishResult;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use PHPUnit\Framework\Assert;

class SocialPublisherFake implements SocialPublisherInterface
{
    protected array $publishedPublications = [];
    protected array $publishedAccounts = [];
    protected bool $capabilitiesResult = true;

    public function publish(MarketingCampaignPostPublication $publication, ClientSocialAccount $account, ?string $correlationId = null): PublishResult
    {
        $this->publishedPublications[] = $publication;
        $this->publishedAccounts[] = $account;

        return new PublishResult(true, 'fake-external-id');
    }

    public function verifyAccountCapabilities(ClientSocialAccount $account): bool
    {
        return $this->capabilitiesResult;
    }

    public function forceCapabilitiesResult(bool $result): void
    {
        $this->capabilitiesResult = $result;
    }

    public function assertNotCalled(): void
    {
        Assert::assertEmpty($this->publishedPublications, 'Publisher was unexpectedly called.');
    }

    public function assertCalled(): void
    {
        Assert::assertNotEmpty($this->publishedPublications, 'Publisher was not called.');
    }
}
