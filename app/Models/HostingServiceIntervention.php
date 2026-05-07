<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HostingServiceIntervention extends Model
{
    use HasFactory;

    protected $fillable = [
        'hosting_service_id',
        'user_id',
        'title',
        'description',
        'intervention_date',
        'cost',
    ];

    protected $casts = [
        'intervention_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function hostingService(): BelongsTo
    {
        return $this->belongsTo(HostingService::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
