<?php

namespace App\Notifications;

use App\Models\Quote;
use Illuminate\Notifications\Notification;

class QuoteUpdatedNotification extends Notification
{
    public function __construct(private Quote $quote) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'quote_updated', 'quote_id' => $this->quote->id,
            'title' => 'Aggiornamento offerta',
            'message' => $this->quote->title.': '.$this->quote->status_label,
            'url' => route('quotes.show', $this->quote)];
    }
}
