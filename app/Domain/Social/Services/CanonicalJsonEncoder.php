<?php

namespace App\Domain\Social\Services;

class CanonicalJsonEncoder
{
    /**
     * Encode array or JsonSerializable object into a stable, canonical JSON string.
     * Recursively sorts keys and uses JSON_UNESCAPED_UNICODE and JSON_UNESCAPED_SLASHES.
     */
    public function encode(mixed $data): string
    {
        if ($data instanceof \JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        $data = json_decode(
            json_encode($data, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $data = $this->sortKeysRecursive($data);

        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function sortKeysRecursive(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        // Se è un array associativo, ordiniamo per chiave
        if (array_keys($data) !== range(0, count($data) - 1)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->sortKeysRecursive($value);
        }

        return $data;
    }
}
