<?php

namespace App\Livewire\Social\Posts;

use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Domain\Social\Actions\RequestSocialPostRegenerationAction;
use App\Domain\Social\Actions\SendSocialPostToClientAction;
use App\Enums\Social\SocialPostCommentVisibility;
use App\Enums\Social\SocialPostCommentType;
use App\Enums\Social\SocialPostStatus;
use Livewire\Component;

class SocialPostShow extends Component
{
    public SocialPost $post;
    
    // Form commento
    public $newCommentBody = '';
    
    // Modal Rigenera
    public $showRegenerateModal = false;
    public $regenerationPrompt = '';

    // Modal Invia Cliente
    public $showSendClientModal = false;
    public $clientLink = '';

    // Modal Pianificazione
    public $showScheduleModal = false;
    public $scheduleDate = '';
    public $schedulePlatform = 'instagram';
    public $scheduleNotes = '';

    public function mount(SocialPost $post)
    {
        $this->authorize('view', $post);
        $this->post = $post->load(['currentVersion', 'versions.creator', 'comments.user', 'project', 'client']);
    }

    public function addInternalComment()
    {
        $this->validate(['newCommentBody' => 'required|string|max:1000']);

        SocialPostComment::create([
            'social_post_id' => $this->post->id,
            'social_post_version_id' => $this->post->current_version_id,
            'user_id' => auth()->id(),
            'body' => $this->newCommentBody,
            'visibility' => SocialPostCommentVisibility::Internal,
            'type' => SocialPostCommentType::Comment,
        ]);

        $this->newCommentBody = '';
        $this->post->refresh();
        
        session()->flash('success', 'Commento aggiunto.');
    }

    public function requestRegeneration(RequestSocialPostRegenerationAction $action)
    {
        $this->authorize('requestRegeneration', $this->post);
        $this->validate(['regenerationPrompt' => 'required|string|min:5|max:1000']);

        $action->execute($this->post, auth()->user(), $this->regenerationPrompt);

        $this->showRegenerateModal = false;
        $this->regenerationPrompt = '';
        $this->post->refresh();

        session()->flash('success', 'Richiesta di rigenerazione inviata a n8n.');
    }

    public function markAsReady()
    {
        $this->authorize('update', $this->post);
        $this->post->update(['status' => SocialPostStatus::ReadyForClient]);
        $this->post->refresh();
        session()->flash('success', 'Post segnato come pronto per il cliente.');
    }

    public function sendToClient(SendSocialPostToClientAction $action)
    {
        $this->authorize('sendToClient', $this->post);
        $link = $action->execute($this->post, auth()->user());
        
        $this->clientLink = $link;
        $this->showSendClientModal = true;
        $this->post->refresh();
        
        session()->flash('success', 'Link cliente generato.');
    }

    public function schedulePost(\App\Domain\Social\Actions\ScheduleSocialPostAction $action)
    {
        $this->validate([
            'scheduleDate' => 'required|date|after:now',
            'schedulePlatform' => 'required|string',
            'scheduleNotes' => 'nullable|string|max:1000',
        ]);

        try {
            $action->execute(
                $this->post, 
                $this->scheduleDate, 
                \App\Enums\Social\SocialPlatform::from($this->schedulePlatform), 
                $this->scheduleNotes, 
                auth()->user()
            );

            $this->showScheduleModal = false;
            $this->post->refresh();
            session()->flash('success', 'Post pianificato con successo nel calendario.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelSlot(\App\Domain\Social\Actions\CancelEditorialSlotAction $action)
    {
        $slot = $this->post->activeEditorialSlot;
        if (!$slot) return;

        try {
            $action->execute($slot, auth()->user());
            $this->post->refresh();
            session()->flash('success', 'Pianificazione annullata.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function publishSlot(\App\Domain\Social\Actions\MarkEditorialSlotPublishedAction $action)
    {
        $slot = $this->post->activeEditorialSlot;
        if (!$slot) return;

        try {
            $action->execute($slot, auth()->user());
            $this->post->refresh();
            session()->flash('success', 'Slot segnato come pubblicato!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.social.posts.social-post-show')
            ->layout('layouts.app', ['title' => 'Dettaglio Post: ' . $this->post->title]);
    }
}
