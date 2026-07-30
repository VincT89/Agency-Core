<?php

namespace Tests\Feature\Integrations\N8n;

use App\Domain\Social\Services\ImageStagerService;
use App\Support\Network\HostResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImageStagerHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('social_media');
        Http::preventStrayRequests();
    }

    public function test_it_streams_a_valid_image_and_calculates_integrity_metadata(): void
    {
        $contents = file_get_contents(base_path('tests/Fixtures/valid.jpg'));
        Http::fake([
            'https://media.example/photo' => Http::response(
                $contents,
                200,
                ['Content-Type' => 'application/octet-stream']
            ),
        ]);
        $this->mock(HostResolver::class)
            ->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->with('media.example')
            ->andReturn('media.example');

        $stager = app(ImageStagerService::class);
        $paths = $stager->downloadAndValidate(['https://media.example/photo']);

        $this->assertCount(1, $paths);
        $this->assertStringEndsWith('.jpg', $paths[0]);
        Storage::disk('local')->assertExists($paths[0]);
        $this->assertSame([
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'mime_type' => 'image/jpeg',
        ], $stager->temporaryMetadata($paths[0]));
    }

    public function test_it_stops_when_the_stream_exceeds_the_incremental_limit_and_removes_partials(): void
    {
        config([
            'n8n_images.max_bytes' => 50,
            'n8n_images.chunk_size_bytes' => 16,
        ]);
        Http::fake([
            '*' => Http::response(str_repeat('x', 100), 200),
        ]);
        $this->mock(HostResolver::class)
            ->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->andReturn('media.example');

        try {
            app(ImageStagerService::class)->downloadAndValidate([
                'https://media.example/oversized',
            ]);
            $this->fail('Expected the oversized stream to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('streamed body size', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('temp/n8n_images'));
    }

    public function test_it_rejects_a_disallowed_port_before_connecting(): void
    {
        $this->mock(HostResolver::class)
            ->shouldNotReceive('resolveAndValidatePublicHost');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('URL port not allowed');

        app(ImageStagerService::class)->downloadAndValidate([
            'https://media.example:8080/photo.jpg',
        ]);
    }

    public function test_it_revalidates_the_host_after_every_redirect(): void
    {
        Http::fake([
            'https://media.example/photo.jpg' => Http::response('', 302, [
                'Location' => 'https://169.254.169.254/latest/meta-data',
            ]),
        ]);

        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->with('media.example')
            ->andReturn('media.example');
        $resolver->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->with('169.254.169.254')
            ->andThrow(new RuntimeException('private address'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private address');

        app(ImageStagerService::class)->downloadAndValidate([
            'https://media.example/photo.jpg',
        ]);
    }

    public function test_it_uses_file_signatures_instead_of_trusting_the_content_type_header(): void
    {
        Http::fake([
            '*' => Http::response('not-an-image', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);
        $this->mock(HostResolver::class)
            ->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->andReturn('media.example');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MIME type not allowed');

        app(ImageStagerService::class)->downloadAndValidate([
            'https://media.example/fake.jpg',
        ]);
    }

    public function test_promotion_copies_with_streams_and_preserves_source_until_cleanup(): void
    {
        Storage::disk('local')->put('temp/n8n_images/source.jpg', 'contents');

        $stager = app(ImageStagerService::class);
        $promoted = $stager->promote(['temp/n8n_images/source.jpg']);

        Storage::disk('local')->assertExists('temp/n8n_images/source.jpg');
        Storage::disk('social_media')->assertExists($promoted[0]);
        $this->assertSame(
            'contents',
            Storage::disk('social_media')->get($promoted[0])
        );
    }
}
