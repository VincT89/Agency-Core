<?php

namespace Tests\Feature\Social;

use App\Domain\Social\DTOs\ClientReviewEmailData;
use App\Mail\Social\MarketingCampaignPostReviewMail;
use Tests\TestCase;

class MarketingCampaignPostReviewMailTest extends TestCase
{
    public function test_mail_envelope_has_correct_subject()
    {
        $data = new ClientReviewEmailData(
            clientName: 'Test Client',
            campaignName: 'Test Campaign',
            postId: 1,
            versionId: 1,
            versionNumber: 1,
            postTitle: 'Test Post Title',
            postCaption: 'Test Caption',
            previewUrls: [],
            reviewUrl: 'https://example.com/review',
            expiresAt: now()->addDays(7)->format('d/m/Y')
        );

        $mail = new MarketingCampaignPostReviewMail($data);

        $this->assertEquals('Revisione Post: Test Post Title', $mail->envelope()->subject);
    }

    public function test_mail_content_is_correct()
    {
        $data = new ClientReviewEmailData(
            clientName: 'Test Client',
            campaignName: 'Test Campaign',
            postId: 1,
            versionId: 1,
            versionNumber: 1,
            postTitle: 'Test Post Title',
            postCaption: 'Test Caption',
            previewUrls: [],
            reviewUrl: 'https://example.com/review',
            expiresAt: now()->addDays(7)->format('d/m/Y')
        );

        $mail = new MarketingCampaignPostReviewMail($data);

        $this->assertEquals('emails.social.marketing-campaign-post-review', $mail->content()->markdown);
        $this->assertEquals($data, $mail->content()->with['data']);
    }
}
