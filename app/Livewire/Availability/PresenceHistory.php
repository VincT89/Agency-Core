<?php

namespace App\Livewire\Availability;

use App\Domain\Availability\Queries\PresenceSummary;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
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

        return view('livewire.availability.presence-history', compact('days', 'summary'));
    }
}
