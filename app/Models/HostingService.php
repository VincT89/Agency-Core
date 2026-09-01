<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostingService extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_LABELS = [
        'domain' => 'Dominio',
        'hosting' => 'Hosting',
        'website' => 'Website',
        'maintenance' => 'Manutenzione',
        'email' => 'Email',
        'dns' => 'DNS',
        'other' => 'Altro',
    ];

    protected $fillable = [
        'client_id',
        'type',
        'service_types',
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
        'service_types' => 'array',
        'renewal_date' => 'date',
        'last_intervention_at' => 'datetime',
        'renewal_cost' => 'decimal:2',
        'resource_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (HostingService $service): void {
            if ($service->isDirty('service_types')) {
                $types = static::normalizeServiceTypes((array) $service->service_types);
            } elseif ($service->isDirty('type')) {
                $types = static::normalizeServiceTypes([(string) $service->type]);
            } else {
                $types = $service->resolved_service_types;
            }

            if ($types === []) {
                return;
            }

            // Il campo singolo resta sincronizzato per i flussi legacy.
            $service->service_types = $types;
            $service->type = $types[0];
        });
    }

    public static function normalizeServiceTypes(array $types): array
    {
        return array_values(array_filter(
            array_keys(static::TYPE_LABELS),
            fn (string $allowedType): bool => in_array($allowedType, $types, true)
        ));
    }

    public function getResolvedServiceTypesAttribute(): array
    {
        $types = static::normalizeServiceTypes((array) $this->service_types);

        if ($types !== []) {
            return $types;
        }

        return static::normalizeServiceTypes([(string) $this->type]);
    }

    public function getServiceTypeLabelsAttribute(): array
    {
        return array_map(
            fn (string $type): string => static::TYPE_LABELS[$type],
            $this->resolved_service_types
        );
    }

    public function hasServiceType(string $type): bool
    {
        return in_array($type, $this->resolved_service_types, true);
    }

    public function scopeWithServiceType(Builder $query, string $type): Builder
    {
        return $query->where(function (Builder $typeQuery) use ($type): void {
            $typeQuery
                ->whereJsonContains('service_types', $type)
                ->orWhere(function (Builder $legacyQuery) use ($type): void {
                    $legacyQuery
                        ->whereNull('service_types')
                        ->where('type', $type);
                });
        });
    }

    public function scopeWithAnyServiceTypeExcept(Builder $query, string $excludedType): Builder
    {
        $includedTypes = array_values(array_diff(array_keys(static::TYPE_LABELS), [$excludedType]));

        return $query->where(function (Builder $typeQuery) use ($includedTypes, $excludedType): void {
            foreach ($includedTypes as $index => $type) {
                if ($index === 0) {
                    $typeQuery->whereJsonContains('service_types', $type);
                } else {
                    $typeQuery->orWhereJsonContains('service_types', $type);
                }
            }

            $typeQuery->orWhere(function (Builder $legacyQuery) use ($excludedType): void {
                $legacyQuery
                    ->whereNull('service_types')
                    ->where('type', '!=', $excludedType);
            });
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(HostingServiceIntervention::class);
    }

    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // Accessors for renewal logic
    public function getIsExpiredAttribute(): bool
    {
        return $this->renewal_date && $this->renewal_date->lt(today());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (! $this->renewal_date || $this->is_expired) {
            return false;
        }

        return $this->renewal_date->diffInDays(today()) <= 30;
    }

    public function getDaysUntilRenewalAttribute(): ?int
    {
        if (! $this->renewal_date) {
            return null;
        }

        return today()->diffInDays($this->renewal_date, false); // false means it can be negative if expired
    }
}
