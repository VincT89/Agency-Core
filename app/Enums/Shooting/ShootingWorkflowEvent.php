<?php

namespace App\Enums\Shooting;

enum ShootingWorkflowEvent: string
{
    case RequestCreated = 'request_created';
    case PhotographerAccepted = 'photographer_accepted';
    case PhotographerRejected = 'photographer_rejected';
    case ClientConfirmed = 'client_confirmed';
    case ClientRejected = 'client_rejected';
}
