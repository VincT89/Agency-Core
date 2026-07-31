<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Str;

class CreateClientAction
{
    public function execute(array $data): Client
    {
        // Genera uno slug univoco basato sul nome
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Client::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $data['slug'] = $slug;

        // Imposta lo status di default se non fornito
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        $logo = data_get($data, 'logo');
        unset($data['logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('clients/logos', 'public');
        }

        if (!empty($data['nextcloud_folder_name'])) {
            $nextcloudService = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
            $mediaPaths = $nextcloudService->ensureClientMediaDirectories(
                $data['nextcloud_folder_name']
            );

            if ($mediaPaths === null) {
                \Illuminate\Support\Facades\Log::warning('Unable to create client Nextcloud media directories', [
                    'folder' => $data['nextcloud_folder_name'],
                ]);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'nextcloud_folder_name' => 'Impossibile creare le cartelle foto e video su Nextcloud. Verifica la connessione o prova con un altro nome.',
                ]);
            }

            $data['nextcloud_photos_path'] = $mediaPaths['photo'];
        }

        return Client::create($data);
    }
}
