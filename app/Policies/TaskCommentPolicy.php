<?php

namespace App\Policies;

use App\Enums\Social\CommentSource;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function delete(User $user, TaskComment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->id
            && $comment->source !== CommentSource::Client
            && $comment->task !== null
            && $user->can('update', $comment->task);
    }
}
