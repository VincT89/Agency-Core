<?php

namespace App\Livewire\Availability;

use App\Domain\Availability\Actions\SaveManualPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PresenceHistory extends Component
{
    use WithPagination;

    #[Locked]
    public string $weekStart;
    #[Locked]
    public bool $team = false;
    #[Locked]
    public ?int $userId = null;

    #[Locked]
    public ?int $editingPresenceId = null;
    #[Locked]
    public ?string $editingRevision = null;
    #[Locked]
    public int $editorVersion = 0;
    public bool $editorOpen = false;
    public array $form = [];
    public ?string $successMessage = null;

    public function beginCreate(): void
    {
        $this->requireAdministrator();
        $this->closeEditor();
        $from = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        $date = CarbonImmutable::today()->betweenIncluded($from, $from->endOfWeek()) ? today()->toDateString() : $from->toDateString();
        $this->form = ['user_id' => $this->userId ?? '', 'date' => $date, 'availability_id' => '',
            'intervals' => [['kind' => 'available', 'starts_at' => '', 'ends_at' => '']], 'reason' => ''];
        $this->editorOpen = true;
        $this->editorVersion++;
        $this->successMessage = null;
    }

    public function editPresence(int $presenceId, SaveManualPresence $action): void
    {
        $this->requireAdministrator();
        $from = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        $session = UserPresenceSession::with('intervals')
            ->whereBetween('date', [$from->toDateString(), $from->endOfWeek()->toDateString()])
            ->when($this->userId !== null, fn ($query) => $query->where('user_id', $this->userId))->findOrFail($presenceId);
        if ($session->effectiveEnd(now()->timestamp) === null) {
            throw ValidationException::withMessages(['presence' => 'La presenza è ancora in corso. Puoi correggerla dopo la conclusione o la disconnessione.']);
        }
        $this->closeEditor();
        $this->editingPresenceId = $session->id;
        $this->editingRevision = $action->revision($session);
        $this->form = [
            'user_id' => $session->user_id, 'date' => $session->date->toDateString(), 'availability_id' => $session->availability_id,
            'intervals' => $session->intervals->sortBy('started_at')->values()->map(fn ($interval) => [
                'kind' => $interval->kind,
                'starts_at' => UserPresenceSession::localTime($interval->started_at)->format('H:i:s'),
                'ends_at' => UserPresenceSession::localTime(min($interval->ended_at ?? $session->confirmedUntil(), $session->confirmedUntil()))->format('H:i:s'),
            ])->all(),
            'reason' => '',
        ];
        $this->editorOpen = true;
        $this->editorVersion++;
        $this->successMessage = null;
    }

    public function updatedForm(mixed $value, ?string $key = null): void
    {
        if ($this->editingPresenceId === null && in_array($key, ['user_id', 'date'], true)) {
            $this->form['availability_id'] = '';
        }
    }

    public function addInterval(): void
    {
        $this->requireAdministrator();
        if ($this->editorOpen && count($this->form['intervals'] ?? []) < 60) {
            $this->form['intervals'][] = ['kind' => 'available', 'starts_at' => '', 'ends_at' => ''];
        }
    }

    public function removeInterval(int $index): void
    {
        $this->requireAdministrator();
        unset($this->form['intervals'][$index]);
        $this->form['intervals'] = array_values($this->form['intervals'] ?? []);
        $this->resetValidation();
    }

    public function closeEditor(): void
    {
        $this->requireAdministrator();
        $this->reset('editingPresenceId', 'editingRevision', 'editorOpen', 'form');
        $this->resetValidation();
    }

    public function savePresence(SaveManualPresence $action): void
    {
        $this->requireAdministrator();
        abort_unless($this->editorOpen, 403);
        $from = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        try {
            Validator::make($this->form, [
                'user_id' => ['required', 'integer'],
                'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$from->toDateString(), 'before_or_equal:'.$from->endOfWeek()->toDateString()],
            ], ['date.*' => 'Scegli un giorno della settimana visualizzata.'])->validate();
            if ($this->userId !== null && (string) ($this->form['user_id'] ?? '') !== (string) $this->userId) {
                throw ValidationException::withMessages(['user_id' => 'Scegli l’operatore selezionato nel filtro della pagina.']);
            }
            $data = $this->form;
            $data['availability_id'] = ($data['availability_id'] ?? '') === '' ? null : $data['availability_id'];
            $action->execute(auth()->user(), $data, $this->editingPresenceId, $this->editingRevision);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['form.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        $this->successMessage = $this->editingPresenceId === null ? 'Presenza inserita. La registrazione è consultabile nello storico.' : 'Presenza corretta. Totali e storico sono aggiornati.';
        $this->closeEditor();
        $this->resetPage('presencePage');
    }

    private function requireAdministrator(): void
    {
        $actor = auth()->user()?->fresh();
        abort_unless($this->team && $actor?->status === 'active' && $actor->canManageSystem(), 403);
    }

    public function render(PresenceSummary $summary)
    {
        abort_unless(auth()->user()?->status === 'active', 403);
        abort_if($this->team && ! auth()->user()->canManageSystem(), 403);
        $from = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        $userId = $this->team ? $this->userId : auth()->id();
        $query = UserPresenceSession::query()
            ->where('date', '>=', $from->toDateString())->where('date', '<', $from->addWeek()->toDateString())
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));
        /** Paginate whole operator-days so every session contributes to the displayed daily total. */
        $days = (clone $query)->select('user_id', 'date')->groupBy('user_id', 'date')
            ->orderByDesc('date')->orderBy('user_id')->paginate(20, ['*'], 'presencePage');
        $sessions = collect();
        if ($days->isNotEmpty()) {
            $sessions = (clone $query)->with(['user:id,name', 'intervals' => fn ($q) => $q->orderBy('started_at')->orderBy('id')])
                ->where(function ($q) use ($days) {
                    foreach ($days as $day) {
                        $q->orWhere(fn ($q) => $q->where('user_id', $day->user_id)->where('date', $day->getRawOriginal('date')));
                    }
                })->orderBy('started_at')->orderBy('id')->get()
                ->groupBy(fn ($session) => $session->user_id.'-'.$session->date->toDateString());
        }
        $dayKey = fn ($day) => $day->user_id.'-'.$day->date->toDateString();
        $days->setCollection($days->getCollection()->filter(fn ($day) => $sessions->has($dayKey($day)))
            ->map(fn ($day) => $summary->historyDay($sessions->get($dayKey($day))))->values());

        $editorUsers = $editorSlots = $corrections = collect();
        $editingSession = null;
        if ($this->team && $this->editorOpen) {
            $this->requireAdministrator();
            $editorUsers = User::query()->when($this->userId !== null, fn ($query) => $query->whereKey($this->userId))->orderBy('name')->get(['id', 'name']);
            if ($this->editingPresenceId !== null) {
                $editingSession = UserPresenceSession::with('user:id,name')->findOrFail($this->editingPresenceId);
                $corrections = $editingSession->corrections()->with('user:id,name')->latest('id')->get();
            } elseif (is_numeric($this->form['user_id'] ?? null) && is_string($this->form['date'] ?? null)) {
                $editorSlots = UserAvailability::where('user_id', $this->form['user_id'])
                    ->whereDate('date', $this->form['date'])->orderBy('starts_at')->get();
            }
        }

        return view('livewire.availability.presence-history', compact('days', 'summary', 'editorUsers', 'editorSlots', 'editingSession', 'corrections', 'from'));
    }
}
