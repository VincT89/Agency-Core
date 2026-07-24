<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$clientId = 187;

$platforms = [\App\Enums\Social\SocialPlatform::Facebook, \App\Enums\Social\SocialPlatform::Instagram, \App\Enums\Social\SocialPlatform::Tiktok];

foreach ($platforms as $platform) {
    \App\Models\ClientSocialAccount::updateOrCreate(
        ['client_id' => $clientId, 'platform' => $platform],
        [
            'api_status' => \App\Enums\Social\SocialApiStatus::Connected,
            'api_provider' => \App\Enums\Social\SocialApiProvider::MetaGraph,
            'access_status' => \App\Enums\Social\SocialAccessStatus::ReadyToPublish,
            'connection_mode' => \App\Enums\Social\SocialConnectionMode::Oauth,
            'provider_account_id' => 'fake_id_' . $platform->value,
            'provider_account_name' => 'Fake ' . ucfirst($platform->value) . ' Account',
            'access_token' => 'fake_token_123456',
            'refresh_token' => 'fake_refresh_123456',
            'token_expires_at' => now()->addDays(30),
            'capabilities' => ['publish_video', 'publish_photo']
        ]
    );
}

echo "Fake social accounts created for client {$clientId}!\n";
