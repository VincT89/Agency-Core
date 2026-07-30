<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction;
use App\Domain\Social\DTOs\CreateManualMarketingCampaignPostVersionData;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Exceptions\Social\StaleMarketingCampaignPostVersionException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateManualMarketingCampaignPostVersionActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateManualMarketingCampaignPostVersionAction $action;

    private User $user;

    private MarketingCampaignPost $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(CreateManualMarketingCampaignPostVersionAction::class);
        $this->user = User::factory()->create();

        $this->post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Draft,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);
    }

    public function test_can_create_first_manual_version_with_null_expected_version()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'test.jpg',
        ]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'New Title',
            caption: 'New Caption',
            hashtags: ['#new'],
            ordered_media_ids: [$media->id],
            author_id: $this->user->id
        );

        $result = $this->action->execute($this->post, $data);

        $this->assertTrue($result->isCreated());
        $this->assertEquals(MarketingCampaignPostStatus::Generated, $this->post->fresh()->status);
        $this->assertEquals($result->version->id, $this->post->fresh()->current_version_id);
        $this->assertEquals('New Title', $result->version->title);
        $this->assertEquals('New Caption', $result->version->caption);
        $this->assertCount(1, $result->version->mediaItems);
        $this->assertEquals($media->id, $result->version->mediaItems->first()->id);
        $this->assertEquals(0, $result->version->mediaItems->first()->pivot->sort_order);
    }

    public function test_throws_stale_exception_if_expected_does_not_match()
    {
        $v1 = $this->post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'caption' => 'Caption',
        ]);
        $this->post->update(['current_version_id' => $v1->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: 99999, // Wrong ID
            title: 'New Title',
            caption: 'New Caption',
            hashtags: null,
            ordered_media_ids: []
        );

        $this->expectException(StaleMarketingCampaignPostVersionException::class);
        $this->action->execute($this->post, $data);
    }

    public function test_returns_unchanged_if_no_modifications_made()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'local',
            'disk' => 'public',
        ]);

        $v1 = $this->post->versions()->create([
            'version_number' => 1,
            'title' => 'V1 Title',
            'caption' => 'V1 Caption',
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v1->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'V1 Title',
            caption: 'V1 Caption',
            hashtags: null,
            ordered_media_ids: [$media->id]
        );

        $result = $this->action->execute($this->post, $data);

        $this->assertTrue($result->isUnchanged());
        $this->assertEquals($v1->id, $result->version->id);
        $this->assertCount(1, $this->post->versions);
    }

    public function test_creates_new_version_on_reorder_only()
    {
        $m1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $m2 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);

        $v1 = $this->post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'caption' => 'Caption',
        ]);
        $v1->mediaItems()->attach([
            $m1->id => ['sort_order' => 0],
            $m2->id => ['sort_order' => 1],
        ]);
        $this->post->update(['current_version_id' => $v1->id]);

        // Swapping order
        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'Title',
            caption: 'Caption',
            hashtags: null,
            ordered_media_ids: [$m2->id, $m1->id]
        );

        $result = $this->action->execute($this->post, $data);

        $this->assertTrue($result->isCreated());
        $this->assertNotEquals($v1->id, $result->version->id);

        $pivot2 = $result->version->mediaItems()->where('marketing_campaign_post_media_id', $m2->id)->first()->pivot;
        $pivot1 = $result->version->mediaItems()->where('marketing_campaign_post_media_id', $m1->id)->first()->pivot;

        $this->assertEquals(0, $pivot2->sort_order);
        $this->assertEquals(1, $pivot1->sort_order);
    }

    public function test_validation_fails_on_duplicate_media()
    {
        $m1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'Title',
            caption: 'Caption',
            hashtags: null,
            ordered_media_ids: [$m1->id, $m1->id]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID media duplicati nella selezione.');
        $this->action->execute($this->post, $data);
    }

    public function test_validation_fails_if_media_does_not_belong_to_post()
    {
        $otherPost = MarketingCampaignPost::factory()->create();
        $m1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $otherPost->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'Title',
            caption: 'Caption',
            hashtags: null,
            ordered_media_ids: [$m1->id]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uno o più media richiesti non esistono o non appartengono a questo post.');
        $this->action->execute($this->post, $data);
    }

    public function test_modifica_esclusiva_caption_crea_nuova_versione_e_mantiene_vecchia()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'Title 1', 'caption' => 'Caption 1']);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v1->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'Title 1',
            caption: 'Caption 2', // changed
            hashtags: null,
            ordered_media_ids: [$media->id]
        );

        $result = $this->action->execute($this->post, $data);

        $this->assertTrue($result->isCreated());
        $this->assertEquals('Caption 2', $result->version->caption);
        $this->assertEquals('Caption 1', $v1->fresh()->caption); // old intatta
    }

    public function test_caption_impostata_esplicitamente_a_null()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'Title 1', 'caption' => 'Caption 1']);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v1->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'Title 1',
            caption: null, // explicit null
            hashtags: null,
            ordered_media_ids: [$media->id]
        );

        $result = $this->action->execute($this->post, $data);
        $this->assertTrue($result->isCreated());
        $this->assertNull($result->version->caption);
    }

    public function test_zero_media_fallisce_se_richiesto()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'Title 1', 'caption' => 'Caption 1']);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v1->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'Title 1',
            caption: 'Caption 1',
            hashtags: null,
            ordered_media_ids: [] // zero media
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('È richiesto almeno un media per creare la versione.');
        $this->action->execute($this->post, $data);
    }

    public function test_image_url_and_path_legacy_generated_correctly()
    {
        $m1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id, 'source' => 'local', 'disk' => 'public', 'path' => 'local.jpg']);
        $m2 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id, 'source' => 'nextcloud', 'nextcloud_share_url' => 'http://nc']);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'T',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$m1->id, $m2->id]
        );

        $result = $this->action->execute($this->post, $data);
        $this->assertTrue($result->isCreated());
        $this->assertEquals('local.jpg', $result->version->image_path);

        // Assert dual write
        $this->post->refresh();
        $this->assertEquals('local.jpg', $this->post->media_path);
        $this->assertEquals('T', $this->post->title);
        $this->assertEquals('C', $this->post->description);
    }

    public function test_hashtags_check_non_solleva_flag_modificato_erroneamente()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'T', 'caption' => 'C', 'hashtags' => ['#test']]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v1->id]);

        // Same hashtags
        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'T',
            caption: 'C',
            hashtags: ['#test'],
            ordered_media_ids: [$media->id]
        );

        $result = $this->action->execute($this->post, $data);
        $this->assertTrue($result->isUnchanged());
    }

    public function test_passaggio_nextcloud_locale_pulisce_campi_legacy()
    {
        $this->post->forceFill([
            'media_source' => 'nextcloud',
            'nextcloud_path' => 'old.jpg',
            'nextcloud_share_url' => 'http://nc',
            'nextcloud_file_id' => '123',
        ])->save();

        $mediaLocal = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id, 'source' => 'local', 'disk' => 'public', 'path' => 'new.jpg']);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'T',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$mediaLocal->id]
        );

        $this->action->execute($this->post, $data);

        $this->post->refresh();
        $this->assertEquals('local', $this->post->media_source);
        $this->assertEquals('new.jpg', $this->post->media_path);
        $this->assertNull($this->post->nextcloud_path);
        $this->assertNull($this->post->nextcloud_share_url);
        $this->assertNull($this->post->nextcloud_file_id);
    }

    public function test_passaggio_locale_nextcloud_pulisce_media_path()
    {
        $this->post->forceFill([
            'media_source' => 'local',
            'media_path' => 'old.jpg',
        ])->save();

        $mediaNc = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id, 'source' => 'nextcloud', 'nextcloud_path' => 'new.jpg', 'nextcloud_share_url' => 'http://nc']);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'T',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$mediaNc->id]
        );

        $this->action->execute($this->post, $data);

        $this->post->refresh();
        $this->assertEquals('nextcloud', $this->post->media_source);
        $this->assertNull($this->post->media_path);
        $this->assertEquals('new.jpg', $this->post->nextcloud_path);
    }

    public function test_nuova_versione_b2_b3_lascia_v3_con_b1_b2()
    {
        $b1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $b2 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $b3 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);

        $v3 = $this->post->versions()->create(['version_number' => 3, 'title' => 'V3']);
        $v3->mediaItems()->attach([
            $b1->id => ['sort_order' => 0],
            $b2->id => ['sort_order' => 1],
        ]);
        $this->post->update(['current_version_id' => $v3->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v3->id,
            title: 'V4',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$b2->id, $b3->id]
        );

        $result = $this->action->execute($this->post, $data);
        $v4 = $result->version;

        $this->assertEquals([$b1->id, $b2->id], $v3->mediaItems()->pluck('marketing_campaign_post_media_id')->toArray());
        $this->assertEquals([$b2->id, $b3->id], $v4->mediaItems()->pluck('marketing_campaign_post_media_id')->toArray());
    }

    public function test_secondo_save_dopo_v4_crea_v5()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v4 = $this->post->versions()->create(['version_number' => 4, 'title' => 'V4']);
        $v4->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $this->post->update(['current_version_id' => $v4->id]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v4->id,
            title: 'V5',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$media->id]
        );

        $result = $this->action->execute($this->post, $data);
        $v5 = $result->version;
        $this->assertEquals(5, $v5->version_number);
    }

    public function test_rollback_su_eccezione_non_modifica_db()
    {
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $this->post->id]);
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 1]);
        $this->post->update(['current_version_id' => $v1->id]);

        // Simula eccezione dopo l'inserimento della versione (durante l'attach del pivot, ad esempio)
        Event::listen('eloquent.created: App\Models\MarketingCampaignPostVersion', function ($model) {
            if ($model->title === 'V2') {
                throw new \Exception('Simulated DB failure after version insertion');
            }
        });

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: $v1->id,
            title: 'V2',
            caption: 'C',
            hashtags: null,
            ordered_media_ids: [$media->id]
        );

        try {
            DB::transaction(function () use ($data) {
                $this->action->execute($this->post, $data);
            });
            $this->fail('Exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated DB failure after version insertion', $e->getMessage());
        }

        $this->assertDatabaseCount('marketing_campaign_post_versions', 1);
        $this->assertEquals($v1->id, $this->post->fresh()->current_version_id);
    }

    public function test_manual_version_with_only_nextcloud_media_sets_image_path_null()
    {
        $m1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => 'http://nc',
            'path' => null,
            'disk' => null,
        ]);

        $data = new CreateManualMarketingCampaignPostVersionData(
            expected_current_version_id: null,
            title: 'Title',
            caption: 'Caption',
            hashtags: null,
            ordered_media_ids: [$m1->id]
        );

        $result = $this->action->execute($this->post, $data);
        $this->assertTrue($result->isCreated());

        // image_path expects a local public path, so for nextcloud it must be null
        $this->assertNull($result->version->image_path);

        // image_url will use the fallback URL resolver which maps to the nextcloud share URL or delivery URL
        $this->assertNotNull($result->version->image_url);
    }
}
