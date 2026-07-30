<?php

namespace Tests\Feature\Social;

use App\Exceptions\Social\NextcloudFileNotFoundException;
use App\Exceptions\Social\NextcloudPermanentFailureException;
use App\Exceptions\Social\NextcloudTemporaryUnavailableException;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NextcloudFileInfoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nextcloud.base_url' => 'https://nextcloud.test',
            'services.nextcloud.username' => 'agency',
            'services.nextcloud.password' => 'secret',
            'services.nextcloud.webdav_path' => '/remote.php/dav/files',
        ]);
    }

    public function test_404_is_classified_as_not_found(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $this->expectException(NextcloudFileNotFoundException::class);

        app(NextcloudService::class)->getFileInfo('/missing.mp4');
    }

    public function test_429_is_classified_as_temporary(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->expectException(NextcloudTemporaryUnavailableException::class);

        app(NextcloudService::class)->getFileInfo('/video.mp4');
    }

    public function test_401_is_classified_as_permanent(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        $this->expectException(NextcloudPermanentFailureException::class);

        app(NextcloudService::class)->getFileInfo('/video.mp4');
    }

    public function test_valid_propfind_returns_strict_metadata_dto(): void
    {
        Http::fake(['*' => Http::response($this->validPropfindXml(), 207)]);

        $info = app(NextcloudService::class)->getFileInfo('/video.mp4');

        $this->assertSame('/video.mp4', $info->path);
        $this->assertSame('42', $info->fileId);
        $this->assertSame('etag-123', $info->etag);
        $this->assertSame('video/mp4', $info->mimeType);
        $this->assertSame(1024, $info->sizeBytes);
    }

    public function test_propfind_cannot_escape_the_configured_user_root(): void
    {
        $xml = str_replace(
            '/remote.php/dav/files/agency/video.mp4',
            '/remote.php/dav/files/another-user/video.mp4',
            $this->validPropfindXml()
        );
        Http::fake(['*' => Http::response($xml, 207)]);

        $this->expectException(NextcloudPermanentFailureException::class);
        $this->expectExceptionMessage('outside the configured user root');

        app(NextcloudService::class)->getFileInfo('/video.mp4');
    }

    public function test_propfind_must_describe_the_exact_requested_path(): void
    {
        $xml = str_replace(
            '/remote.php/dav/files/agency/video.mp4',
            '/remote.php/dav/files/agency/other.mp4',
            $this->validPropfindXml()
        );
        Http::fake(['*' => Http::response($xml, 207)]);

        $this->expectException(NextcloudPermanentFailureException::class);
        $this->expectExceptionMessage('unexpected path');

        app(NextcloudService::class)->getFileInfo('/video.mp4');
    }

    private function validPropfindXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<d:multistatus xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:response>
    <d:href>/remote.php/dav/files/agency/video.mp4</d:href>
    <d:propstat>
      <d:prop>
        <d:resourcetype/>
        <d:getcontenttype>video/mp4</d:getcontenttype>
        <d:getcontentlength>1024</d:getcontentlength>
        <d:getetag>"etag-123"</d:getetag>
        <oc:fileid>42</oc:fileid>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>
XML;
    }
}
