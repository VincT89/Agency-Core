<?php

namespace App\Livewire\Admin\Shooting;

use App\Domain\Shooting\Actions\ClientConfirmAction;
use App\Domain\Shooting\Actions\MarkClientInformedAction;
use App\Domain\Shooting\Services\ShootingClientCommunicationService;
use App\Enums\Shooting\ShootClientContactChannel;
use App\Models\Shooting\Shoot;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ShootShow extends Component
{
    use AuthorizesRequests;

    public Shoot $shoot;

    public string $clientChannel = 'whatsapp';

    public function mount(Shoot $shoot): void
    {
        if (! auth()->user()->canManageSystem()) {
            abort(403);
        }

        $this->authorize('view', $shoot);
        $this->shoot = $shoot;
        $this->reloadShoot();

        $client = $this->shoot->clientRecord();
        $this->clientChannel = ($client?->normalized_phone || $client?->phone)
            ? ShootClientContactChannel::Whatsapp->value
            : ShootClientContactChannel::Email->value;
    }

    public function markClientInformed(MarkClientInformedAction $action): void
    {
        $this->authorize('confirmClient', $this->shoot);
        $this->validate([
            'clientChannel' => ['required', Rule::enum(ShootClientContactChannel::class)],
        ]);

        $action->execute(
            $this->shoot,
            ShootClientContactChannel::from($this->clientChannel)
        );
        $this->reloadShoot();

        session()->flash('success', 'Comunicazione al cliente registrata.');
    }

    public function confirmForClient(ClientConfirmAction $action): void
    {
        $this->authorize('confirmClient', $this->shoot);
        $action->execute($this->shoot, true, auth()->id());
        $this->reloadShoot();

        session()->flash('success', 'Shooting confermato. Evento e task creati.');
    }

    public function rejectForClient(ClientConfirmAction $action): void
    {
        $this->authorize('confirmClient', $this->shoot);
        $action->execute($this->shoot, false, auth()->id());
        $this->reloadShoot();

        session()->flash('success', 'Rifiuto registrato. Il marketing può proporre nuove date.');
    }

    public function render(ShootingClientCommunicationService $communication)
    {
        return view('livewire.admin.shooting.shoot-show', [
            'communication' => $communication->for($this->shoot),
            'clientChannels' => ShootClientContactChannel::cases(),
        ])->layout('layouts.app', ['title' => 'Dettaglio Shooting']);
    }

    private function reloadShoot(): void
    {
        $this->shoot->refresh()->load([
            'project.client',
            'marketingCampaign.client',
            'slots',
            'selectedSlot',
            'photographer',
            'creator',
            'calendarEvent',
            'task',
        ]);
    }
}
