<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Services\AuditLogService;

class AttachmentObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Attachment $attachment): void
    {
        if ($attachment->attachable) {
            $userName = auth()->user()?->name ?? 'Sistema';
            $desc = "{$userName} ha caricato l'allegato {$attachment->original_name}";
            $this->auditLog->log('uploaded_attachment', $attachment->attachable, null, null, $desc, auth()->id() ?: $attachment->uploaded_by);
        }
    }

    public function deleting(Attachment $attachment): void
    {
        if ($attachment->attachable) {
            $userName = auth()->user()?->name ?? 'Sistema';
            $desc = "{$userName} ha eliminato l'allegato {$attachment->original_name}";
            $this->auditLog->log('deleted_attachment', $attachment->attachable, null, null, $desc, auth()->id());
        }
    }
}
