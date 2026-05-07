<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;
use App\Models\Client;

class ChatbotClient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function marketingCampaigns()
    {
        return $this->hasMany(ChatbotMarketingCampaign::class);
    }

    public function marketingPosts()
    {
        return $this->hasMany(ChatbotMarketingPost::class);
    }

    public function tickets()
    {
        return $this->hasMany(ChatbotTicket::class);
    }
}
