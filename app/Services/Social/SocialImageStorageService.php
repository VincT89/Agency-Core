<?php

namespace App\Services\Social;

use App\Domain\Social\Services\ImageStagerService;
use InvalidArgumentException;
use RuntimeException;

class SocialImageStorageService
{
    public function __construct(
        private readonly ImageStagerService $imageStager
    ) {}

    /**
     * Scarica, valida e archivia un'immagine sul disco social privato.
     */
    public function downloadAndStore(
        string $url,
        string $disk = 'social_media'
    ): string {
        if ($disk !== 'social_media') {
            throw new InvalidArgumentException(
                'Le immagini social possono essere archiviate solo sul disco privato.'
            );
        }

        $temporaryPaths = $this->imageStager->downloadAndValidate([$url]);

        try {
            $promotedPaths = $this->imageStager->promote($temporaryPaths);
            $path = $promotedPaths[0] ?? null;

            if (! is_string($path) || $path === '') {
                throw new RuntimeException(
                    'Archiviazione privata dell’immagine non riuscita.'
                );
            }

            return $path;
        } finally {
            $this->imageStager->deleteTemporary($temporaryPaths);
        }
    }
}
