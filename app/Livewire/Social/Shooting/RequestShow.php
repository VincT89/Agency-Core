<?php

namespace App\Livewire\Social\Shooting;

use App\Domain\Shooting\Actions\ClientConfirmAction;
use App\Domain\Shooting\Actions\MarkClientInformedAction;
use App\Domain\Shooting\Actions\ReopenShootProposalAction;
use App\Domain\Shooting\Services\ShootingClientCommunicationService;
use App\Enums\Shooting\ShootClientContactChannel;
use App\Models\Shooting\Shoot;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RequestShow extends Component
{
    use AuthorizesRequests;

    public Shoot $shoot;

    public string $clientChannel = 'whatsapp';

    public ?int $revisionPhotographerId = null;

    public array $revisionSlots = [];

    public function mount(Shoot $shoot): void
    {
        if (auth()->user()->isPhotographer() && ! auth()->user()->canManageSystem()) {
            abort(403, 'Accesso negato: sezione riservata al team marketing.');
        }

        $this->authorize('view', $shoot);
        $this->shoot = $shoot;
        $this->reloadShoot();

        $client = $this->shoot->clientRecord();
        $this->clientChannel = ($client?->normalized_phone || $client?->phone)
            ? ShootClientContactChannel::Whatsapp->value
            : ShootClientContactChannel::Email->value;
        $this->revisionPhotographerId = $this->shoot->photographer_id;
        $this->addRevisionSlot();
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

        session()->flash('success', 'Cliente confermato. Calendario e task sono stati creati.');
    }

    public function rejectForClient(ClientConfirmAction $action): void
    {
        $this->authorize('confirmClient', $this->shoot);
        $action->execute($this->shoot, false, auth()->id());
        $this->reloadShoot();

        session()->flash('success', 'Rifiuto registrato. Ora puoi proporre nuove date.');
    }

    public function addRevisionSlot(): void
    {
        $this->revisionSlots[] = [
            'date' => '',
            'period' => 'morning',
        ];
    }

    public function removeRevisionSlot(int $index): void
    {
        if (count($this->revisionSlots) === 1) {
            return;
        }

        unset($this->revisionSlots[$index]);
        $this->revisionSlots = array_values($this->revisionSlots);
    }

    public function reopenProposal(ReopenShootProposalAction $action): void
    {
        $this->authorize('revise', $this->shoot);
        $this->validate([
            'revisionPhotographerId' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'photographer')
                    ->where('status', 'active')),
            ],
            'revisionSlots' => 'required|array|min:1',
            'revisionSlots.*.date' => 'required|date|after_or_equal:today',
            'revisionSlots.*.period' => 'required|in:morning,intermediate,afternoon,full_day',
        ]);

        $action->execute(
            $this->shoot,
            $this->revisionPhotographerId,
            $this->revisionSlots
        );
        $this->reloadShoot();
        $this->revisionSlots = [];
        $this->addRevisionSlot();

        session()->flash('success', 'Nuove date inviate al fotografo.');
    }

    public function render(ShootingClientCommunicationService $communication)
    {
        return view('livewire.social.shooting.request-show', [
            'communication' => $communication->for($this->shoot),
            'clientChannels' => ShootClientContactChannel::cases(),
            'photographers' => User::where('role', 'photographer')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
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
