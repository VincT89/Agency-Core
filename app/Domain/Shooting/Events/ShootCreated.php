<?php

namespace App\Domain\Shooting\Events;

use App\Models\Shooting\Shoot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShootCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shoot $shoot
    ) {}
}
