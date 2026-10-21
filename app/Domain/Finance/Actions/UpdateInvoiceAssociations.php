<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Core\Actions\CreateProjectAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\HostingService;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceAssociations
{
    public function execute(Invoice $invoice, array $data): void
    {
        Gate::authorize('manageAssociations', $invoice);
        $data = Validator::make($data, [
            'assignment_type' => 'sometimes|required|in:project,new_project,domain,hosting,none,campaign',
            'project_id' => [isset($data['assignment_type']) ? 'required_if:assignment_type,project' : 'present', 'nullable', 'integer', 'exists:projects,id'],
            'hosting_service_id' => 'required_if:assignment_type,domain,hosting|nullable|integer',
            'new_project_name' => 'required_if:assignment_type,new_project|nullable|string|max:255',
            'new_project_description' => 'nullable|string|max:5000',
            'replace_campaign' => 'sometimes|boolean',
            'services' => 'sometimes|array|max:1000',
            'services.*' => 'nullable|integer',
        ])->validate();
        DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            Gate::authorize('manageAssociations', $invoice);
            $mode = $data['assignment_type'] ?? null;
            $project = in_array($mode, [null, 'project'], true) && filled($data['project_id'] ?? null) ? Project::findOrFail($data['project_id']) : null;
            $service = null;
            if (in_array($mode, ['domain', 'hosting'], true)) {
                $service = HostingService::withTrashed()->withServiceType($mode)->lockForUpdate()->find($data['hosting_service_id']);
                if (! $service || $service->client_id !== $invoice->client_id
                    || ($service->trashed() && $service->id !== $invoice->hosting_service_id)) {
                    throw ValidationException::withMessages(['hosting_service_id' => 'Seleziona un dominio o hosting esistente del cliente della fattura.']);
                }
            }
            if ($mode === 'campaign' && ! $invoice->marketing_campaign_id) {
                throw ValidationException::withMessages(['assignment_type' => 'Questa fattura non ha una campagna collegata.']);
            }
            if ($mode === 'new_project') {
                Gate::authorize('create', Project::class);
                $name = trim($data['new_project_name']);
                if ($name === '' || Project::where('client_id', $invoice->client_id)->where('name', $name)->exists()) {
                    throw ValidationException::withMessages(['new_project_name' => 'Inserisci un nome nuovo oppure seleziona il progetto già esistente.']);
                }
                $project = app(CreateProjectAction::class)->execute([
                    'client_id' => $invoice->client_id, 'name' => $name, 'status' => 'active',
                    'description' => $data['new_project_description'] ?? null, 'members' => [auth()->id()],
                    'roles' => [auth()->id() => 'sponsor'],
                ]);
            }
            if ($project && ! $project->isInvoiceContainer() && $project->client_id !== $invoice->client_id) {
                throw ValidationException::withMessages(['project_id' => 'Il progetto non appartiene al cliente della fattura.']);
            }
            if ($invoice->marketing_campaign_id && ($project || $service || $mode === 'none') && ! ($data['replace_campaign'] ?? false)) {
                throw ValidationException::withMessages(['replace_campaign' => 'Conferma la sostituzione del collegamento alla campagna.']);
            }
            if ($mode === null && $invoice->hosting_service_id && ($project || ! empty($data['services']))) {
                throw ValidationException::withMessages(['project_id' => 'Questa fattura è collegata a un servizio. Usa il modulo dei collegamenti per sostituirlo.']);
            }
            $imported = $invoice->fiscal_status === InvoiceFiscalStatus::Imported;
            $items = $invoice->items()->lockForUpdate()->get()->keyBy('id');
            $importedItems = collect($invoice->import_metadata['items'] ?? []);
            $oldLinks = $imported ? $invoice->importedServiceLinks()->pluck('hosting_service_id', 'line_index')->all()
                : $items->pluck('hosting_service_id', 'id')->all();
            foreach ($mode === null ? ($data['services'] ?? []) : [] as $index => $serviceId) {
                if (! ctype_digit((string) $index) || ($imported ? ! $importedItems->has((int) $index) : ! $items->has((int) $index))) {
                    throw ValidationException::withMessages(['services' => 'Una voce non appartiene a questa fattura. Ricarica la pagina.']);
                }
                if (filled($serviceId)) {
                    $service = HostingService::withTrashed()->lockForUpdate()->find($serviceId);
                    if (! $service || $service->client_id !== $invoice->client_id
                        || ($service->trashed() && (int) ($oldLinks[$index] ?? 0) !== (int) $serviceId)) {
                        throw ValidationException::withMessages(["services.$index" => 'Seleziona un servizio del cliente della fattura.']);
                    }
                }
                if ($imported) {
                    filled($serviceId)
                        ? $invoice->importedServiceLinks()->updateOrCreate(['line_index' => (int) $index], ['hosting_service_id' => $serviceId])
                        : $invoice->importedServiceLinks()->where('line_index', (int) $index)->delete();
                } else {
                    $items->get((int) $index)->update(['hosting_service_id' => $serviceId ?: null]);
                }
            }
            $oldProject = $invoice->project_id;
            $oldCampaign = $invoice->marketing_campaign_id;
            $oldService = $invoice->hosting_service_id;
            $invoice->project_id = $project?->id;
            if ($mode !== null) {
                /** A deliberate new destination replaces legacy per-line links without modifying fiscal content. */
                $invoice->hosting_service_id = $service?->id;
                $invoice->importedServiceLinks()->delete();
                $invoice->items()->whereNotNull('hosting_service_id')->update(['hosting_service_id' => null]);
            }
            if ($mode !== 'campaign' && ($data['replace_campaign'] ?? false)) {
                $invoice->marketing_campaign_id = null;
            }
            $invoice->save();
            /** Keep payment attribution aligned without altering amounts, receipt dates or fiscal snapshots. */
            $invoice->payments()->update(['project_id' => $project?->id]);
            app(AuditLogService::class)->log('associations_changed', $invoice,
                ['project_id' => $oldProject, 'hosting_service_id' => $oldService, 'marketing_campaign_id' => $oldCampaign, 'services' => $oldLinks],
                ['project_id' => $project?->id, 'hosting_service_id' => $invoice->hosting_service_id, 'marketing_campaign_id' => $invoice->marketing_campaign_id, 'services' => $mode === null ? ($data['services'] ?? $oldLinks) : []],
                'Aggiornati progetto e servizi collegati alla fattura.');
        });
    }
}
