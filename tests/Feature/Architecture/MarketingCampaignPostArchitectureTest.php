<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MarketingCampaignPostArchitectureTest extends TestCase
{
    /**
     * Verifica che i consumer critici non leggano più le proprietà legacy per la media resolution.
     */
    #[Test]
    public function critical_consumers_must_not_read_legacy_media_properties()
    {
        $criticalFiles = [
            'app/Domain/Social/Builders/MarketingCampaignPostMediaPayloadBuilder.php',
            'app/Domain/Social/Actions/SubmitMarketingCampaignPostToN8nAction.php',
            'app/Domain/Social/Actions/RequestMarketingCampaignPostRegenerationAction.php',
            'app/Domain/Social/Services/MetaPreflightService.php',
            'app/Domain/Social/Services/TikTokPreflightService.php',
            'app/Livewire/Social/MarketingCampaigns/MarketingCampaignShow.php',
            'app/Livewire/Social/MarketingCampaigns/MarketingCampaignPostShow.php',
            'resources/views/livewire/social/marketing-campaigns/marketing-campaign-show.blade.php',
            'resources/views/livewire/social/marketing-campaigns/marketing-campaign-post-show.blade.php',
            'resources/views/livewire/public/marketing-campaign-post-review.blade.php',
            'resources/views/emails/social/marketing-campaign-post-review.blade.php',
            'app/Mail/Social/MarketingCampaignPostReviewMail.php',
            'app/Domain/Social/Actions/SendMarketingCampaignPostToClientAction.php',
            'app/Domain/Social/Actions/CreateManualMarketingCampaignPostVersionAction.php',
            'app/Domain/Social/Actions/AddMarketingCampaignPostVersionFromN8nAction.php',
            'app/Livewire/Public/MarketingCampaignPostReview.php'
        ];

        $forbiddenPatterns = [
            '/\$post->media_url/i' => '$post->media_url',
            '/\$post->preview_url/i' => '$post->preview_url',
            '/\$currentVersion->image_url\b/i' => '$currentVersion->image_url',
            '/\$currentVersion->image_urls\b/i' => '$currentVersion->image_urls',
            '/->orderedMediaItems/i' => 'orderedMediaItems',
            '/app\([^)]*MarketingCampaignPostVersionMediaResolver::class\)/i' => 'Service Locator app(Resolver)',
            '/->resolveMediaItems\(/i' => 'resolveMediaItems'
        ];

        $requiredPatterns = [
            '->resolveForPost(',
            '->resolveForVersion(',
            'MarketingCampaignPostMediaPayloadBuilder'
        ];

        $violations = [];
        $missingFiles = [];

        foreach ($criticalFiles as $filePath) {
            $fullPath = base_path($filePath);
            if (!file_exists($fullPath)) {
                $missingFiles[] = $filePath;
                continue;
            }

            $content = file_get_contents($fullPath);

            foreach ($forbiddenPatterns as $pattern => $description) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "File: {$filePath} contains forbidden usage of: {$description}";
                }
            }

            if (str_ends_with($filePath, '.php') && !str_contains($filePath, '.blade.php') && !str_contains($filePath, 'Mail/')) {
                $hasRequired = false;
                foreach ($requiredPatterns as $req) {
                    if (str_contains($content, $req)) {
                        $hasRequired = true;
                        break;
                    }
                }
                
                if (!$hasRequired) {
                    $violations[] = "File: {$filePath} does not use any of the required media resolution patterns (resolveForPost, resolveForVersion, or PayloadBuilder).";
                }
            }
        }

        $this->assertEmpty($missingFiles, "I seguenti file critici non esistono:\n" . implode("\n", $missingFiles));

        $this->assertEmpty(
            $violations,
            "Trovate violazioni architetturali nei consumer critici:\n" . implode("\n", $violations)
        );
    }
}
