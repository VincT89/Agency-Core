<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'body', 'source',
        'delivery_channel', 'delivery_status', 'delivery_requested_at',
        'delivered_at', 'delivery_error', 'external_message_id', 'idempotency_key'
    ];

    protected $casts = [
        'source' => \App\Enums\Social\CommentSource::class,
        'delivery_requested_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
