<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

class ChatbotMarketingPost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function chatbotClient()
    {
        return $this->belongsTo(ChatbotClient::class);
    }
}
