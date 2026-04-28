<?php

namespace App\Models;

use App\Enums\Social\MarketingProjectType;
use App\Enums\Social\MarketingProjectStatus;
use App\Enums\Social\PublicationMode;
use Illuminate\Database\Eloquent\Model;

class MarketingProject extends Model
{
    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'title',
        'brief',
        'description',
        'type',
        'status',
        'platforms',
        'publication_mode',
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
            'submitted_to_n8n_at' => 'datetime',
        ];
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
}
