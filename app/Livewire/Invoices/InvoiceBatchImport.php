<?php

namespace App\Livewire\Invoices;

use App\Domain\Finance\Services\InvoiceImportArchive;
use App\Domain\Finance\Services\InvoiceImportBatch;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoiceBatchImport extends Component
{
    use WithFileUploads;

    public $file;

    public bool $confirmed = false;

    #[Locked]
    public ?string $token = null;

    #[Locked]
    public array $rows = [];

    #[Locked]
    public bool $completed = false;

    public function mount(): void
    {
        Gate::authorize('create', Invoice::class);
    }

    public function updatedFile(): void
    {
        Gate::authorize('create', Invoice::class);
        if ($this->token) {
            $this->forgetPreview();
        }
        $this->reset('token', 'rows', 'confirmed', 'completed');
        $this->resetValidation();
    }

    public function preview(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['file' => 'required|file|max:10240|extensions:zip']);
        $this->updatedFile();
        $files = app(InvoiceImportArchive::class)->files($this->file->getRealPath());
        $this->rows = app(InvoiceImportBatch::class)->preview($files);
        $this->token = (string) Str::uuid();
        foreach ($files as &$source) {
            $source['content'] = base64_encode($source['content']);
        }
        unset($source);
        /** Bounded cache entries also fit database MEDIUMTEXT and small cache item limits. */
        $chunks = str_split(json_encode($files, JSON_THROW_ON_ERROR), 512 * 1024);
        $expires = now()->addMinutes(20);
        if (! Cache::put($this->cacheKey(), count($chunks), $expires)) {
            throw ValidationException::withMessages(['file' => 'Impossibile conservare l’anteprima. Riprova.']);
        }
        foreach ($chunks as $index => $chunk) {
            if (! Cache::put($this->cacheKey().':'.$index, $chunk, $expires)) {
                $this->forgetPreview();
                throw ValidationException::withMessages(['file' => 'Impossibile conservare l’anteprima. Riprova.']);
            }
        }
    }

    public function import(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->validate(['confirmed' => 'accepted']);
        $count = $this->token ? Cache::get($this->cacheKey()) : null;
        if (! is_int($count) || $count < 1 || $count > 64 || $this->completed) {
            throw ValidationException::withMessages(['file' => 'L’anteprima è scaduta. Leggi nuovamente lo ZIP.']);
        }
        $encoded = '';
        for ($index = 0; $index < $count; $index++) {
            $chunk = Cache::get($this->cacheKey().':'.$index);
            if (! is_string($chunk)) {
                throw ValidationException::withMessages(['file' => 'L’anteprima è scaduta. Leggi nuovamente lo ZIP.']);
            }
            $encoded .= $chunk;
        }
        $files = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        foreach ($files as &$source) {
            $content = base64_decode($source['content'], true);
            if (! is_string($content)) {
                throw ValidationException::withMessages(['file' => 'L’anteprima non è valida. Leggi nuovamente lo ZIP.']);
            }
            $source['content'] = $content;
        }
        unset($source);
        $this->rows = app(InvoiceImportBatch::class)->import($files);
        $this->forgetPreview();
        $this->completed = true;
        $this->token = null;
    }

    private function cacheKey(): string
    {
        return 'invoice-batch:'.auth()->id().':'.$this->token;
    }

    private function forgetPreview(): void
    {
        $count = Cache::get($this->cacheKey());
        if (is_int($count) && $count > 0 && $count <= 64) {
            for ($index = 0; $index < $count; $index++) {
                Cache::forget($this->cacheKey().':'.$index);
            }
        }
        Cache::forget($this->cacheKey());
    }

    public function render()
    {
        Gate::authorize('create', Invoice::class);

        return view('livewire.invoices.invoice-batch-import')->layout('layouts.app', ['title' => 'Importa XML da ZIP']);
    }
}
