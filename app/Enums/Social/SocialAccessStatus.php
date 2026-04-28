<?php

namespace App\Enums\Social;

enum SocialAccessStatus: string
{
    case NotStarted = 'not_started';
    case WaitingClient = 'waiting_client';
    case PartialAccess = 'partial_access';
    case AccessGranted = 'access_granted';
    case ReadyToPublish = 'ready_to_publish';
    case AccessProblem = 'access_problem';

    public function label(): string
    {
        return match($this) {
            self::NotStarted => 'Da configurare',
            self::WaitingClient => 'In attesa del cliente',
            self::PartialAccess => 'Accesso parziale',
            self::AccessGranted => 'Accesso concesso',
            self::ReadyToPublish => 'Pronto per la pubblicazione',
            self::AccessProblem => 'Problema di accesso',
        };
    }
    
    public function badgeColor(): string
    {
        return match($this) {
            self::ReadyToPublish => 'var(--green)',
            self::AccessGranted => 'var(--teal)',
            self::WaitingClient => 'var(--orange)',
            self::PartialAccess => 'var(--yellow)',
            self::AccessProblem => 'var(--red)',
            self::NotStarted => 'var(--text3)',
        };
    }
}
