<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SocialPostWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public \App\Models\SocialPost $post,
        public string $action, // es. 'received', 'client_approved', 'changes_requested'
        public string $message
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Per ora solo DB, in futuro mail/slack
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'social_post_' . $this->action,
            'title' => 'Aggiornamento Social Post',
            'message' => $this->message . " ({$this->post->title})",
            'post_id' => $this->post->id,
            'url' => route('social.posts.show', $this->post)
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
