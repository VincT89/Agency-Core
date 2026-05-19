<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

#[Fillable([
    'name',
    'slug',
    'company_name',
    'email',
    'phone',
    'normalized_phone',
    'reference_person',
    'vat_number',
    'tax_code',
    'address',
    'city',
    'postal_code',
    'province',
    'country',
    'billing_email',
    'pec',
    'sdi_code',
    'status',
    'notes',
    'logo_path',
    'activity_description',
    'nextcloud_folder_name',
    'nextcloud_photos_path',
])]
class Client extends Model
{
    use HasFactory;

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path 
            ? url(route('media.public', ['path' => $this->logo_path], false)) 
            : null;
    }

    public function toN8nPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'logo_url' => $this->logo_url,
            'activity_description' => $this->activity_description,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            default => ucfirst((string) $this->status),
        };
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(ClientSocialAccount::class);
    }

    public function facebookAccount()
    {
        return $this->hasOne(ClientSocialAccount::class)->where('platform', \App\Enums\Social\SocialPlatform::Facebook->value);
    }

    public function instagramAccount()
    {
        return $this->hasOne(ClientSocialAccount::class)->where('platform', \App\Enums\Social\SocialPlatform::Instagram->value);
    }

    public function tiktokAccount()
    {
        return $this->hasOne(ClientSocialAccount::class)->where('platform', \App\Enums\Social\SocialPlatform::Tiktok->value);
    }

    public function socialAccountFor(string $platform): ?ClientSocialAccount
    {
        $enumPlatform = \App\Enums\Social\SocialPlatform::tryFrom($platform);
        return $this->socialAccounts->first(function ($account) use ($enumPlatform, $platform) {
            return $account->platform === $enumPlatform || $account->platform === $platform;
        });
    }



    public function marketingCampaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class);
    }

    public function isMetaReady(): bool
    {
        $fb = $this->socialAccountFor(\App\Enums\Social\SocialPlatform::Facebook->value);
        $ig = $this->socialAccountFor(\App\Enums\Social\SocialPlatform::Instagram->value);

        if (!$fb || !$ig) {
            return false;
        }

        if (!$fb->isReadyToPublish() || !$ig->isReadyToPublish()) {
            return false;
        }

        $fbIsOauth = $fb->connection_mode === \App\Enums\Social\SocialConnectionMode::Oauth;
        $igIsOauth = $ig->connection_mode === \App\Enums\Social\SocialConnectionMode::Oauth;

        // Se entrambi sono connessi via OAuth e sono ready, sono ok
        if ($fbIsOauth && $igIsOauth) {
            return true;
        }

        // Se anche solo uno non è OAuth, si fa il fallback sulla logica manuale legacy
        if (blank($fb->business_manager_id) || blank($ig->business_manager_id)) {
            return false;
        }

        if ($fb->business_manager_id !== $ig->business_manager_id) {
            return false;
        }

        if (
            $fb->access_method !== \App\Enums\Social\SocialAccessMethod::MetaBusiness ||
            $ig->access_method !== \App\Enums\Social\SocialAccessMethod::MetaBusiness
        ) {
            return false;
        }

        return true;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return $query;
        }

        return $query->whereHas('projects.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    public function hostingServices(): HasMany
    {
        return $this->hasMany(HostingService::class);
    }
}
