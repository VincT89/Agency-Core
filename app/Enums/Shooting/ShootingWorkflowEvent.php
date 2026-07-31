<?php

namespace App\Enums\Shooting;

enum ShootingWorkflowEvent: string
{
    case RequestCreated = 'request_created';
    case PhotographerAccepted = 'photographer_accepted';
    case PhotographerRejected = 'photographer_rejected';
    case ClientInformed = 'client_informed';
    case ClientConfirmed = 'client_confirmed';
    case ClientRejected = 'client_rejected';
    case RequestReopened = 'request_reopened';
}
