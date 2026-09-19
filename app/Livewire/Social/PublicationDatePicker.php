<?php

namespace App\Livewire\Social;

use App\Domain\Social\Services\MarketingCampaignPostCalendarDateResolver;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class PublicationDatePicker extends Component
{
    #[Modelable]
    public ?string $value = null;

    #[Locked]
    public int $campaignId;

    #[Locked]
    public ?int $postId = null;

    #[Locked]
    public bool $disabled = false;

    public string $month;

    public bool $open = false;

    public function mount(MarketingCampaign $campaign, ?int $postId = null, bool $disabled = false): void
    {
        $this->authorize('view', $campaign);
        if ($postId !== null) {
            abort_unless($campaign->posts()->whereKey($postId)->exists(), 404);
        }
        $this->campaignId = $campaign->id;
        $this->postId = $postId;
        $this->disabled = $disabled;
        $this->month = ($this->selectedDate() ?? CarbonImmutable::today())->startOfMonth()->toDateString();
    }

    private function selectedDate(): ?CarbonImmutable
    {
        if (! $this->value || ! CarbonImmutable::hasFormat($this->value, 'Y-m-d')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $this->value);
    }

    public function toggle(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->open = ! $this->open;
        if ($this->open) {
            $this->month = ($this->selectedDate() ?? CarbonImmutable::today())->startOfMonth()->toDateString();
        }
    }

    public function moveMonth(int $direction): void
    {
        $this->validate(['month' => 'required|date_format:Y-m-d']);
        $this->month = CarbonImmutable::parse($this->month)->startOfMonth()
            ->addMonths($direction < 0 ? -1 : 1)->toDateString();
    }

    public function selectDate(?string $date): void
    {
        if ($this->disabled) {
            return;
        }
        validator(['date' => $date], ['date' => 'nullable|date_format:Y-m-d'])->validate();
        $this->value = $date;
        $this->open = false;
    }

    public function render()
    {
        $campaign = MarketingCampaign::findOrFail($this->campaignId);
        $this->authorize('view', $campaign);
        $this->validate(['month' => 'required|date_format:Y-m-d']);
        $month = CarbonImmutable::parse($this->month)->startOfMonth();
        $start = $month->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $counts = [];

        if ($this->open) {
            $posts = MarketingCampaignPost::query()
                ->notArchived()
                ->where('status', '!=', MarketingCampaignPostStatus::Cancelled)
                ->when($this->postId, fn ($query) => $query->whereKeyNot($this->postId))
                ->whereHas('campaign', fn ($query) => $query->visibleTo(auth()->user())->where('client_id', $campaign->client_id))
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhereBetween('published_at', [$start, $end])
                        ->orWhereHas('successfulPublications', fn ($query) => $query->whereBetween('published_at', [$start, $end]));
                })
                ->with('successfulPublications')
                ->get();
            $resolver = app(MarketingCampaignPostCalendarDateResolver::class);
            foreach ($posts as $post) {
                $date = $resolver->resolve($post)?->toDateString();
                if ($date && $date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $counts[$date] = ($counts[$date] ?? 0) + 1;
                }
            }
        }

        $weeks = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addWeek()) {
            $days = [];
            $total = 0;
            for ($offset = 0; $offset < 7; $offset++) {
                $date = $cursor->addDays($offset);
                $count = $counts[$date->toDateString()] ?? 0;
                $days[] = ['date' => $date, 'count' => $count];
                $total += $count;
            }
            $weeks[] = ['days' => $days, 'total' => $total];
        }

        return view('livewire.social.publication-date-picker', [
            'monthDate' => $month, 'weeks' => $weeks, 'selectedDate' => $this->selectedDate(),
        ]);
    }
}
