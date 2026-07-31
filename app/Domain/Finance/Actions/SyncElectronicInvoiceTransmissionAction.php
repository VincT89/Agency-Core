<?php

namespace App\Domain\Finance\Actions;

use App\Exceptions\Finance\ElectronicInvoiceSubmissionException;
use App\Domain\Finance\Services\ElectronicInvoiceStatusUpdater;
use App\Models\ElectronicInvoiceTransmission;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;

class SyncElectronicInvoiceTransmissionAction
{
    public function __construct(
        private readonly ArubaInvoiceClient $client,
        private readonly ElectronicInvoiceStatusUpdater $statusUpdater,
    ) {}

    public function execute(
        ElectronicInvoiceTransmission $transmission,
    ): ElectronicInvoiceTransmission {
        if ($transmission->mode !== 'live' || blank($transmission->upload_filename)) {
            throw new ElectronicInvoiceSubmissionException(
                'Questa operazione non dispone ancora di un riferimento Aruba da aggiornare.'
            );
        }

        $detail = $this->client->invoiceDetail($transmission->upload_filename);
        $transmission = $this->statusUpdater->applyDetail($transmission, $detail);
        $notifications = $this->client->notifications($transmission->upload_filename);

        foreach ((array) ($notifications['notifications'] ?? []) as $notification) {
            if (is_array($notification)) {
                $transmission = $this->statusUpdater->applyNotification(
                    $transmission,
                    $notification,
                );
            }
        }

        return $transmission->fresh(['events', 'invoice']);
    }
}
