<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

class ChatbotMarketingCampaign extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function chatbotClient()
    {
        return $this->belongsTo(ChatbotClient::class);
    }
}
