<?php

namespace Tests\Unit\Social;

use App\Domain\Social\Services\PublicationDiagnosticPresenter;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\MarketingCampaignPostPublication;
use PHPUnit\Framework\TestCase;

class PublicationDiagnosticPresenterTest extends TestCase
{
    public function test_it_extracts_only_safe_tiktok_diagnostic_fields(): void
    {
        $publication = new MarketingCampaignPostPublication;
        $publication->setRawAttributes([
            'platform' => SocialPlatform::Tiktok->value,
            'status' => PublicationStatus::NeedsManualReview->value,
            'external_task_id' => 'publish-id-that-must-not-be-returned',
            'error_message' => 'Errore API. access_token=very-secret-token',
            'provider_last_response' => json_encode([
                'status' => 'API_ERROR',
                'http_status' => 401,
                'request_id' => 'request-safe-123',
                'response_data' => [
                    'error' => ['code' => 'access_token_invalid'],
                    'access_token' => 'very-secret-token',
                ],
            ], JSON_THROW_ON_ERROR),
            'response_snapshot' => json_encode([], JSON_THROW_ON_ERROR),
        ], true);

        $diagnostic = (new PublicationDiagnosticPresenter)->present($publication);

        $this->assertSame(
            'Errore API. access_token=[REDACTED]',
            $diagnostic['message']
        );
        $this->assertTrue($diagnostic['initialization_accepted']);
        $this->assertSame('API_ERROR', $diagnostic['provider_status']);
        $this->assertSame('Errore API durante il controllo', $diagnostic['provider_status_label']);
        $this->assertSame('access_token_invalid', $diagnostic['provider_code']);
        $this->assertSame('request-safe-123', $diagnostic['request_reference']);
        $this->assertSame(401, $diagnostic['http_status']);
        $this->assertStringNotContainsString(
            'publish-id-that-must-not-be-returned',
            json_encode($diagnostic, JSON_THROW_ON_ERROR)
        );
        $this->assertStringNotContainsString(
            'very-secret-token',
            json_encode($diagnostic, JSON_THROW_ON_ERROR)
        );
    }
}
