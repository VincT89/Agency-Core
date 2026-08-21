<?php

namespace App\Livewire\Availability;

use App\Domain\Availability\Actions\PersistUserAvailabilityAction;
use App\Models\UserAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class MyAvailability extends Component
{
    use AuthorizesRequests;

    public $weekStart = '';

    public $date = '';

    public $startsAt = '08:00';

    public $endsAt = '14:00';

    public $editingId = null;

    public bool $editorOpen = false;

    public $successMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UserAvailability::class);

        $this->weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
        $this->date = CarbonImmutable::today()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->subWeek()->toDateString();
        $this->resetEditorState();
        $this->successMessage = null;
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->addWeek()->toDateString();
        $this->resetEditorState();
        $this->successMessage = null;
    }

    public function currentWeek(): void
    {
        $this->weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
        $this->resetEditorState();
        $this->successMessage = null;
    }

    public function beginCreate(?string $date = null): void
    {
        $this->resetEditorState();
        $this->editorOpen = true;
        $this->successMessage = null;

        if ($date !== null) {
            $this->date = $date;
        }
    }

    public function editAvailability(int $availabilityId): void
    {
        $availability = UserAvailability::query()->findOrFail($availabilityId);
        $this->authorize('update', $availability);

        $this->resetValidation();
        $this->editingId = $availability->getKey();
        $this->editorOpen = true;
        $this->date = $availability->date->toDateString();
        $this->startsAt = $availability->startsAtForInput();
        $this->endsAt = $availability->endsAtForInput();
        $this->weekStart = $availability->date->toImmutable()->startOfWeek()->toDateString();
        $this->successMessage = null;
    }

    public function cancelEdit(): void
    {
        $this->resetEditorState();
        $this->successMessage = null;
    }

    public function save(PersistUserAvailabilityAction $action): void
    {
        $validated = $this->validate();
        $availability = null;

        if ($this->editingId !== null) {
            $availability = UserAvailability::query()->findOrFail((int) $this->editingId);
            $this->authorize('update', $availability);
        } else {
            $this->authorize('create', UserAvailability::class);
        }

        $wasEditing = $availability !== null;

        $action->execute(auth()->user(), [
            'date' => $validated['date'],
            'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'],
        ], $availability);

        $this->weekStart = CarbonImmutable::parse($validated['date'])->startOfWeek()->toDateString();
        $this->resetEditorState();
        $this->successMessage = $wasEditing
            ? 'Disponibilità aggiornata.'
            : 'Disponibilità aggiunta.';
    }

    public function deleteAvailability(int $availabilityId): void
    {
        $availability = UserAvailability::query()->findOrFail($availabilityId);
        $this->authorize('delete', $availability);

        $availability->delete();

        if ((int) $this->editingId === $availabilityId) {
            $this->resetEditorState();
        }

        $this->successMessage = 'Disponibilità eliminata.';
    }

    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i', 'after:startsAt'],
        ];
    }

    protected function messages(): array
    {
        return [
            'date.required' => 'Indica la data.',
            'date.date_format' => 'La data non è valida.',
            'date.after_or_equal' => 'Puoi inserire disponibilità soltanto da oggi in avanti.',
            'startsAt.required' => 'Indica l’orario di inizio.',
            'startsAt.date_format' => 'L’orario di inizio non è valido.',
            'endsAt.required' => 'Indica l’orario di fine.',
            'endsAt.date_format' => 'L’orario di fine non è valido.',
            'endsAt.after' => 'L’orario di fine deve essere successivo a quello di inizio.',
        ];
    }

    public function render()
    {
        $weekStart = $this->weekStartDate();
        $weekEnd = $weekStart->endOfWeek();
        $days = collect(range(0, 6))->map(
            fn (int $offset) => $weekStart->addDays($offset)
        );

        $availabilities = UserAvailability::query()
            ->where('user_id', auth()->id())
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (UserAvailability $availability) => $availability->date->toDateString());

        return view('livewire.availability.my-availability', [
            'days' => $days,
            'weekStartDate' => $weekStart,
            'weekEndDate' => $weekEnd,
            'availabilitiesByDate' => $availabilities,
        ])->layout('layouts.app', ['title' => 'Le mie disponibilità']);
    }

    private function weekStartDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->weekStart)->startOfWeek();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfWeek();
        }
    }

    private function resetEditorState(): void
    {
        $this->editorOpen = false;
        $this->editingId = null;
        $this->startsAt = '08:00';
        $this->endsAt = '14:00';
        $this->resetValidation();
    }
}
