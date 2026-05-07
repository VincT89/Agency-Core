<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HostingService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'domain',
        'provider',
        'location',
        'status',
        'access_url',
        'username',
        'password',
        'renewal_date',
        'renewal_cost',
        'resource_cost',
        'billing_cycle',
        'notes',
        'last_intervention_at',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'renewal_date' => 'date',
        'last_intervention_at' => 'datetime',
        'renewal_cost' => 'decimal:2',
        'resource_cost' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(HostingServiceIntervention::class);
    }
}
