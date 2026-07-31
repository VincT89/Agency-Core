<?php

namespace App\Domain\Social\Services;

use Livewire\Wireable;

class PreflightResult implements Wireable
{
    public function __construct(
        public bool $isPass,
        public array $checks = [],
        public array $errors = []
    ) {}

    public static function pass(array $checks): self
    {
        return new self(true, $checks, []);
    }

    public static function fail(array $checks, array $errors): self
    {
        return new self(false, $checks, $errors);
    }

    public function addCheck(string $name, bool $passed, ?string $message = null): void
    {
        $this->checks[$name] = [
            'passed' => $passed,
            'message' => $message,
        ];

        if (!$passed && $message) {
            $this->errors[] = $message;
            $this->isPass = false;
        }
    }

    public function userFacingErrors(): array
    {
        $messages = [];

        foreach ($this->checks as $name => $check) {
            if ($check['passed'] ?? false) {
                continue;
            }

            $messages[] = match (true) {
                in_array($name, ['account_capabilities', 'token_valid'], true) =>
                    'Ricollega l\'account social prima di pubblicare.',
                $name === 'platform' =>
                    'La piattaforma selezionata non è configurata correttamente.',
                in_array($name, ['media_resolution'], true) =>
                    'Alcuni file del post non sono più disponibili. Selezionali di nuovo.',
                in_array($name, ['instagram_media_present', 'media_present'], true) =>
                    'Aggiungi almeno un file compatibile con la piattaforma selezionata.',
                $name === 'carousel_count_limit' =>
                    'Instagram accetta al massimo 10 file per post.',
                $name === 'photo_count_limit' =>
                    'Riduci il numero di foto selezionate.',
                $name === 'no_mixed_media' =>
                    'Per TikTok usa solo un video oppure solo fotografie.',
                $name === 'single_video_only' =>
                    'Per TikTok seleziona un solo video.',
                $name === 'video_format' =>
                    'Il formato del video non è supportato. Usa un file MP4 o WebM.',
                in_array($name, ['video_size'], true) || str_starts_with($name, 'media_video_size_') =>
                    'Il video selezionato è troppo grande.',
                str_starts_with($name, 'media_format_') =>
                    'Una delle immagini ha un formato non supportato. Usa JPG o PNG.',
                str_starts_with($name, 'media_size_') =>
                    'Una delle immagini è troppo grande.',
                str_starts_with($name, 'media_url_') =>
                    'Uno dei file non è raggiungibile. Selezionalo di nuovo.',
                $name === 'reel_media_valid' =>
                    'Un Reel richiede un solo file video.',
                default =>
                    'La configurazione della pubblicazione non è completa.',
            };
        }

        return array_values(array_unique($messages));
    }

    public function toLivewire()
    {
        return [
            'isPass' => $this->isPass,
            'checks' => $this->checks,
            'errors' => $this->errors,
        ];
    }

    public static function fromLivewire($value)
    {
        return new self(
            $value['isPass'],
            $value['checks'],
            $value['errors']
        );
    }
}
