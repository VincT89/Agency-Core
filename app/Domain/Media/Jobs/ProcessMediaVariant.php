<?php

namespace App\Domain\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Attachment;
use App\Domain\Media\Contracts\MediaProcessorContract;

class ProcessMediaVariant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Attachment $attachment
    ) {}

    public function handle(MediaProcessorContract $processor): void
    {
        if ($processor->shouldProcess($this->attachment)) {
            $processor->process($this->attachment);
        }
    }
}
