<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

class ChatbotTicket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function chatbotClient()
    {
        return $this->belongsTo(ChatbotClient::class);
    }
}
