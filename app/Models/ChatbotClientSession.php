<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotClientSession extends Model
{
    protected $fillable = [
        'client_id',
        'session_type',
        'session_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
