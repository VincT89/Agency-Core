<?php

namespace App\Models\Chatbot;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class ChatbotProject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function chatbotClient()
    {
        return $this->belongsTo(ChatbotClient::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
