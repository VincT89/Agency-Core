<?php

namespace Tests\Unit;

use App\Domain\Social\Services\ImageStagerService;
use App\Services\Social\SocialImageStorageService;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class SocialImageStorageServiceTest extends TestCase
{
    public function test_it_uses_the_hardened_stager_and_private_disk(): void
    {
        $stager = Mockery::mock(ImageStagerService::class);
        $stager->shouldReceive('downloadAndValidate')
            ->once()
            ->with(['https://media.example/image.jpg'])
            ->andReturn(['temp/n8n_images/image.jpg']);
        $stager->shouldReceive('promote')
            ->once()
            ->with(['temp/n8n_images/image.jpg'])
            ->andReturn(['marketing_campaigns/posts/2026/07/image.jpg']);
        $stager->shouldReceive('deleteTemporary')
            ->once()
            ->with(['temp/n8n_images/image.jpg']);

        $path = (new SocialImageStorageService($stager))->downloadAndStore(
            'https://media.example/image.jpg'
        );

        $this->assertSame(
            'marketing_campaigns/posts/2026/07/image.jpg',
            $path
        );
    }

    public function test_it_rejects_public_storage(): void
    {
        $stager = Mockery::mock(ImageStagerService::class);
        $stager->shouldNotReceive('downloadAndValidate');

        $this->expectException(InvalidArgumentException::class);

        (new SocialImageStorageService($stager))->downloadAndStore(
            'https://media.example/image.jpg',
            'public'
        );
    }
}
