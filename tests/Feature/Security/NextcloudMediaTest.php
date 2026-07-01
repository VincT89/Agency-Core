<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class NextcloudMediaTest extends TestCase
{
    public function test_nextcloud_prevents_path_traversal_with_dot_dot_slash(): void
    {
        $this->markTestIncomplete('TODO: implement path traversal test');
    }

    public function test_nextcloud_prevents_backslash_in_paths(): void
    {
        $this->markTestIncomplete('TODO: implement backslash test');
    }

    public function test_nextcloud_rejects_unallowed_mime_types(): void
    {
        $this->markTestIncomplete('TODO: implement mime type test');
    }

    public function test_nextcloud_rejects_unallowed_file_extensions(): void
    {
        $this->markTestIncomplete('TODO: implement file extension test');
    }

    public function test_nextcloud_returns_404_for_non_existent_files(): void
    {
        $this->markTestIncomplete('TODO: implement 404 test');
    }

    public function test_nextcloud_prevents_access_to_paths_outside_permitted_root(): void
    {
        $this->markTestIncomplete('TODO: implement root access test');
    }

    public function test_media_signed_url_access_fails_if_expired(): void
    {
        $this->markTestIncomplete('TODO: implement signed URL expiration test');
    }

    public function test_media_signed_url_access_fails_if_signature_is_modified(): void
    {
        $this->markTestIncomplete('TODO: implement signed URL signature test');
    }

    public function test_private_media_is_not_accessible_without_authorization(): void
    {
        $this->markTestIncomplete('TODO: implement private media authorization test');
    }

    public function test_public_media_is_accessible(): void
    {
        $this->markTestIncomplete('TODO: implement public media test');
    }
}
