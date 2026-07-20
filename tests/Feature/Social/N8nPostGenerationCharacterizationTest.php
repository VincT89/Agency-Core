<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Domain\Social\Services\ImageStagerService;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class N8nPostGenerationCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private AddMarketingCampaignPostVersionFromN8nAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');

        // Mock the ImageStagerService to avoid real HTTP requests and return mock files
        $mockStager = $this->mock(ImageStagerService::class);
        
        $mockStager->shouldReceive('downloadAndValidate')
            ->andReturnUsing(function ($urls) {
                $temps = [];
                foreach ($urls ?? [] as $i => $url) {
                    $temps[] = "temp/downloaded_{$i}.jpg";
                }
                return $temps;
            });
            
        $mockStager->shouldReceive('promote')
            ->andReturnUsing(function ($temps) {
                $promoted = [];
                foreach ($temps as $i => $temp) {
                    $path = "marketing_campaigns/posts/promoted_{$i}_" . uniqid() . ".jpg";
                    Storage::disk('public')->put($path, 'fake image content');
                    $promoted[] = $path;
                }
                return $promoted;
            });
            
        $mockStager->shouldReceive('deleteTemporary')->andReturnNull();
        $mockStager->shouldReceive('deletePromoted')->andReturnNull();

        $this->action = app(AddMarketingCampaignPostVersionFromN8nAction::class);
    }

    public function test_it_creates_version_media_and_pivot_on_initial_callback()
    {
        $post = MarketingCampaignPost::factory()->create();
        
        $data = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Full,
            title: 'Initial Title',
            caption: 'Initial Caption',
            hashtags: ['#initial'],
            imageUrls: ['https://example.com/image1.jpg'],
            externalGenerationId: 'ext-123',
            requestId: 'req-123',
            promptUsed: 'A prompt',
            rawPayload: ['foo' => 'bar']
        );

        $result = $this->action->execute($data);

        $this->assertEquals('created', $result->outcome);
        $this->assertNotNull($result->version);
        
        // Assert version was created
        $this->assertDatabaseHas('marketing_campaign_post_versions', [
            'marketing_campaign_post_id' => $post->id,
            'caption' => 'Initial Caption',
            'version_number' => 1,
        ]);
        
        $version = $result->version;

        // Assert media was created and pivoted
        $this->assertCount(1, $version->mediaItems);
        $media = $version->mediaItems->first();
        $this->assertEquals('n8n', $media->source);
        $this->assertNotNull($media->path);
        
        // Check current version ID is updated
        $this->assertEquals($version->id, $post->fresh()->current_version_id);
    }

    public function test_it_reuses_media_on_caption_only_regeneration()
    {
        $post = MarketingCampaignPost::factory()->create();
        
        // Prima versione (Full)
        $data1 = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Full,
            title: 'T1',
            caption: 'C1',
            hashtags: [],
            imageUrls: ['https://example.com/img.jpg'],
            externalGenerationId: 'ext-1',
            requestId: 'req-1',
            promptUsed: null,
            rawPayload: []
        );
        $version1 = $this->action->execute($data1)->version;
        $media1 = $version1->mediaItems->first();

        // Seconda versione (Caption only)
        $data2 = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Caption,
            title: 'T2',
            caption: 'C2',
            hashtags: [],
            imageUrls: [],
            externalGenerationId: 'ext-2',
            requestId: 'req-2',
            promptUsed: null,
            rawPayload: []
        );
        $version2 = $this->action->execute($data2)->version;

        $this->assertEquals(2, $version2->version_number);
        $this->assertEquals('C2', $version2->caption);
        
        // Verifica riuso media
        $this->assertCount(1, $version2->mediaItems);
        $media2 = $version2->mediaItems->first();
        
        $this->assertEquals($media1->id, $media2->id); // Identità preservata
        $this->assertEquals(1, MarketingCampaignPostMedia::count()); // Nessun nuovo media creato
    }

    public function test_it_creates_new_media_and_preserves_old_on_image_only_regeneration()
    {
        $post = MarketingCampaignPost::factory()->create();
        
        // Prima versione
        $data1 = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Full,
            title: 'T1',
            caption: 'C1',
            hashtags: [],
            imageUrls: ['https://example.com/img1.jpg'],
            externalGenerationId: 'ext-1',
            requestId: 'req-1',
            promptUsed: null,
            rawPayload: []
        );
        $version1 = $this->action->execute($data1)->version;
        $media1 = $version1->mediaItems->first();

        // Seconda versione (Image only)
        $data2 = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Image,
            title: '', // Image only regeneration might have empty title/caption in payload, Action copies from current
            caption: '', 
            hashtags: [],
            imageUrls: ['https://example.com/img2.jpg'],
            externalGenerationId: 'ext-2',
            requestId: 'req-2',
            promptUsed: null,
            rawPayload: []
        );
        $version2 = $this->action->execute($data2)->version;

        $this->assertEquals(2, $version2->version_number);
        $this->assertEquals('C1', $version2->caption); // Copiato dalla versione 1
        
        $this->assertCount(1, $version2->mediaItems);
        $media2 = $version2->mediaItems->first();
        
        $this->assertNotEquals($media1->id, $media2->id); // Nuovo media
        $this->assertEquals(2, MarketingCampaignPostMedia::count()); // Entrambi i media esistono
        
        // Verifica conservazione vecchio media e pivot
        $this->assertCount(1, $version1->fresh()->mediaItems);
        $this->assertEquals($media1->id, $version1->fresh()->mediaItems->first()->id);
    }
}
