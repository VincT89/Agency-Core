<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingProjectMedia extends Model
{
    protected $fillable = [
        'marketing_project_id',
        'source',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }
}
