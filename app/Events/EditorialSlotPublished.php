<?php

namespace App\Events;

use App\Models\EditorialSlot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EditorialSlotPublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EditorialSlot $slot
    ) {}
}
