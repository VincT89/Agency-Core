<?php

namespace App\Domain\Media\Contracts;

use App\Models\Attachment;

interface MediaProcessorContract
{
    /**
     * Elaborates a media file creating standard variants (e.g. thumbnails, web-optimized).
     */
    public function process(Attachment $attachment): void;
    
    /**
     * Determines whether the given attachment requires media processing.
     */
    public function shouldProcess(Attachment $attachment): bool;
}
