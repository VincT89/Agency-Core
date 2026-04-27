<?php

namespace App\Models\Shooting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\Shooting\ShootSlotStatus;
use App\Enums\Shooting\ShootSlotPeriod;

class ShootSlot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shoot_id',
        'date',
        'period',
        'starts_at',
        'ends_at',
        'status',
        'responded_at',
        'photographer_note',
    ];

    protected $casts = [
        'date' => 'date',
        'period' => ShootSlotPeriod::class,
        'status' => ShootSlotStatus::class,
        'responded_at' => 'datetime',
    ];

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }
}
