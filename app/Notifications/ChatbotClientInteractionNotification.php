<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;

class ChatbotClientInteractionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Model $entity,
        public string $interactionType // 'comment', 'approval', 'change_request'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isTicket = $this->entity instanceof Ticket;
        $title = $isTicket ? 'Nuovo commento ticket dal cliente' : 'Feedback post dal cliente';
        
        $message = 'Il cliente ha interagito con ';
        if ($isTicket) {
            $message .= 'il ticket #' . $this->entity->id . '.';
            $url = route('tickets.show', $this->entity);
        } else {
            if ($this->interactionType === 'approval') {
                $title = 'Post approvato dal cliente';
                $message = 'Il cliente ha approvato il post: ' . ($this->entity->title ?? 'Senza Titolo');
            } elseif ($this->interactionType === 'change_request') {
                $title = 'Richiesta modifiche post';
                $message = 'Il cliente ha richiesto modifiche per il post: ' . ($this->entity->title ?? 'Senza Titolo');
            } else {
                $message = 'Il cliente ha commentato il post: ' . ($this->entity->title ?? 'Senza Titolo');
            }
            $url = route('marketing-campaigns.posts.show', ['campaign' => $this->entity->marketing_campaign_id, 'post' => $this->entity->id]);
        }

        return [
            'type'    => 'chatbot_client_interaction',
            'title'   => $title,
            'message' => $message,
            'url'     => $url,
            'entity_type' => get_class($this->entity),
            'entity_id' => $this->entity->id,
            'interaction_type' => $this->interactionType,
        ];
    }
}
