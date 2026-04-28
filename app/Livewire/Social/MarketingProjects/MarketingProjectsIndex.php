<?php

namespace App\Livewire\Social\MarketingProjects;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MarketingProject;

class MarketingProjectsIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = MarketingProject::query()
            ->with(['client', 'creator'])
            ->latest();

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.social.marketing-projects.index', [
            'projects' => $query->paginate(15)
        ]);
    }
}
