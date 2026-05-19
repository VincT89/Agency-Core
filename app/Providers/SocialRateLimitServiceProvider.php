<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SocialRateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Rate limiter isolato per tenant e account
        // Chiave: meta-publishing:{client_id}:{platform}:{account_id}
        RateLimiter::for('meta-publishing', function ($job) {
            if (isset($job->post) && isset($job->platform)) {
                $clientId = $job->post->campaign->client_id;
                $account = $job->post->campaign->client->socialAccountFor($job->platform);
                $accountId = $account ? $account->provider_account_id : 'unknown';
                
                return Limit::perMinute(30)->by("meta-publishing:{$clientId}:{$job->platform}:{$accountId}");
            }
            return Limit::perMinute(60);
        });
    }
}
