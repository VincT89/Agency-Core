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
            $root = rtrim($nextcloudService->mediaRoot('photo'), '/');
            $data['nextcloud_photos_path'] = $root . '/' . $data['nextcloud_folder_name'];

            if (!$nextcloudService->ensureDirectoryExists($data['nextcloud_photos_path'])) {
                \Illuminate\Support\Facades\Log::warning('Unable to create client Nextcloud folder', [
                    'folder' => $data['nextcloud_folder_name'],
                    'path' => $data['nextcloud_photos_path'],
                ]);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'nextcloud_folder_name' => 'Impossibile creare la cartella su Nextcloud. Verifica la connessione o prova con un altro nome.',
                ]);
            }
        }

        return Client::create($data);
    }
}
