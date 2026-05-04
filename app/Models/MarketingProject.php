<?php

namespace App\Models;

use App\Enums\Social\MarketingProjectType;
use App\Enums\Social\MarketingProjectStatus;
use App\Enums\Social\PublicationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Shooting\Shoot;

class MarketingProject extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'title',
        'brief',
        'description',
        'type',
        'service_type',
        'campaign_structure',
        'status',
        'platforms',
        'publication_mode',
        'service_options',
        'n8n_request_id',
        'submitted_to_n8n_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MarketingProjectType::class,
            'status' => MarketingProjectStatus::class,
            'publication_mode' => PublicationMode::class,
            'platforms' => 'array',
            'service_options' => 'array',
            'submitted_to_n8n_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::deleting(function ($marketingProject) {
            $marketingProject->socialPosts->each(fn($post) => $post->delete());
            $marketingProject->shoots->each(fn($shoot) => $shoot->delete());
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editorialPlan()
    {
        return $this->hasOne(EditorialPlan::class);
    }

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }

    public function shoots(): HasMany
    {
        return $this->hasMany(Shoot::class, 'marketing_project_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MarketingProjectMedia::class);
    }

    public function getServiceOption(string $key, $default = null)
    {
        return data_get($this->service_options, $key, $default);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return $query;
        }

        return $query->whereHas('project.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
