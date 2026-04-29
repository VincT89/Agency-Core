<?php

namespace App\Domain\Media\Contracts;

use App\Models\Attachment;

interface MediaProcessorContract
{
    // Elabora un file multimediale creando le varianti standard
    public function process(Attachment $attachment): void;
    
    // Verifica se l'allegato richiede l'elaborazione multimediale
    public function shouldProcess(Attachment $attachment): bool;
}
