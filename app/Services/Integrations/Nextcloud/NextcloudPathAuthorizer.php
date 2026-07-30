<?php

namespace App\Services\Integrations\Nextcloud;

use App\Models\Client;
use App\Models\User;

class NextcloudPathAuthorizer
{
    public function canAccess(User $user, string $path): bool
    {
        if (! $this->isInsideConfiguredMediaRoots($path)) {
            return false;
        }

        if ($user->canAccessAllProjects() || $user->isMarketing()) {
            return true;
        }

        return Client::query()
            ->visibleTo($user)
            ->get([
                'id',
                'nextcloud_folder_name',
                'nextcloud_photos_path',
            ])
            ->contains(function (Client $client) use ($path): bool {
                foreach ([
                    $client->nextcloud_photos_path,
                    $client->nextcloud_videos_path,
                ] as $clientRoot) {
                    if (is_string($clientRoot) && $this->isInside($path, $clientRoot)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function isInsideConfiguredMediaRoots(string $path): bool
    {
        return $this->isInside(
            $path,
            (string) config('services.nextcloud.photos_root', '/FotoClienti')
        ) || $this->isInside(
            $path,
            (string) config('services.nextcloud.videos_root', '/VideoClienti')
        );
    }

    private function isInside(string $path, string $root): bool
    {
        $normalizedPath = '/'.ltrim(str_replace('\\', '/', $path), '/');
        $normalizedRoot = rtrim('/'.ltrim(str_replace('\\', '/', $root), '/'), '/');

        return $normalizedPath === $normalizedRoot
            || str_starts_with($normalizedPath, $normalizedRoot.'/');
    }
}
