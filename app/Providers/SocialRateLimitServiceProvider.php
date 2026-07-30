<?php

namespace App\Providers;

use App\Models\MarketingCampaignPostPublication;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SocialRateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('social-publishing', function ($job) {
            if (isset($job->publicationId) && $job->publicationId) {
                $publication = MarketingCampaignPostPublication::query()
                    ->with('post.campaign')
                    ->find($job->publicationId);

                if ($publication) {
                    $clientId = $publication->post?->campaign?->client_id
                        ?? 'unknown';
                    $platform = $publication->platform->value;
                    $accountId = $publication->client_social_account_id;

                    return Limit::perMinute(30)->by(
                        "social-publishing:{$clientId}:{$platform}:{$accountId}"
                    );
                }
            }

            if (isset($job->post) && isset($job->platform)) {
                $clientId = $job->post->campaign->client_id;
                $account = $job->post->campaign->client->socialAccountFor($job->platform);
                $accountId = $account ? $account->provider_account_id : 'unknown';

                return Limit::perMinute(30)->by(
                    "social-publishing:{$clientId}:{$job->platform}:{$accountId}"
                );
            }

            return Limit::perMinute(5)->by(
                'social-publishing:unresolved:'.get_class($job)
            );
        });
    }
}
