<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

class ChatbotMarketingPost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
        'published_at' => 'datetime',
        'publishing_platforms' => 'array',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function chatbotClient()
    {
        return $this->belongsTo(ChatbotClient::class);
    }
}
