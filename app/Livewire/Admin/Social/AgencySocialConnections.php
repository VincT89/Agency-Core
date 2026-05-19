<?php

namespace App\Livewire\Admin\Social;

use Livewire\Component;
use App\Models\AgencySocialConnection;
use App\Domain\Social\Actions\SyncMetaAssetsAction;
use Illuminate\Support\Facades\Log;

class AgencySocialConnections extends Component
{
    public function syncConnection($connectionId)
    {
        $connection = AgencySocialConnection::findOrFail($connectionId);

        try {
            $action = app(SyncMetaAssetsAction::class);
            $result = $action->execute($connection);

            if ($result->isSuccessful()) {
                session()->flash('success', "Sincronizzazione completata: {$result->newCreated} nuovi, {$result->updated} aggiornati, {$result->revoked} rimossi.");
            } else {
                session()->flash('error', "Errore durante la sincronizzazione: {$result->errorMessage}");
            }
        } catch (\Exception $e) {
            Log::error('AgencySocialConnections sync error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Errore imprevisto durante la sincronizzazione.');
        }
    }

    public function revokeConnection(int $connectionId): void
    {
        $connection = AgencySocialConnection::findOrFail($connectionId);

        \Illuminate\Support\Facades\DB::transaction(function () use ($connection) {
            $connection->update([
                'status' => \App\Enums\Social\AgencyConnectionStatus::Revoked,
                'requires_reauth' => true,
                'last_api_error' => 'Connessione revocata manualmente da pannello admin.',
            ]);

            $connection->assets()->update([
                'is_active' => false,
                'is_assignable' => false,
                'revoked_at' => now(),
            ]);
        });

        session()->flash('success', 'Connessione Meta revocata. Gli asset collegati sono stati disattivati.');
    }

    public function render()
    {
        return view('livewire.admin.social.agency-social-connections', [
            'connections' => AgencySocialConnection::with('assets', 'connectedBy')->get(),
        ])->layout('layouts.app');
    }
}
