<x-app-layout :title="'Collegamento fattura '.$invoice->display_number">
    @php($selectedAssignment = old('assignment_type', $assignmentType))
    @php($selectedCampaign = old('marketing_campaign_id', $invoice->marketing_campaign_id))
    <x-page-header>
        <x-slot:title>Collegamento della fattura</x-slot:title>
        <x-slot:meta>Fattura {{ $invoice->display_number }} · {{ $invoice->client?->name }}</x-slot:meta>
        <x-slot:actions><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Torna alla fattura</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        <form method="POST" action="{{ route('invoices.associations.update', $invoice) }}" class="finance-assignment-form"
            x-data="{ assignment: @js($selectedAssignment), campaign: @js($selectedCampaign) }"
            @finance-selected="if ($event.detail.type === 'campaigns') campaign = $event.detail.id">
            @csrf @method('PATCH')
            @if($errors->any())<div class="u-alert-error u-mb-md" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <p class="u-mb-lg">Scegli a cosa collegare l’intera fattura. Il collegamento aggiorna anche i riepiloghi per progetto.</p>
            <x-form-group label="Collega fattura a" name="assignment_type" required>
                <select id="field-assignment-type" name="assignment_type" class="form-sel" x-model="assignment" required>
                    @foreach(['project' => 'Progetto esistente', 'campaign' => 'Progetto marketing esistente', 'new_project' => 'Nuovo progetto', 'domain' => 'Dominio esistente', 'hosting' => 'Hosting esistente', 'none' => 'Nessun collegamento'] as $value => $label)
                        @if($value !== 'new_project' || auth()->user()->can('create', \App\Models\Project::class))<option value="{{ $value }}" @selected($selectedAssignment === $value)>{{ $label }}</option>@endif
                    @endforeach
                </select>
            </x-form-group>
            <fieldset class="finance-assignment-fields" x-show="assignment === 'project'" :disabled="assignment !== 'project'" @disabled($selectedAssignment !== 'project') x-cloak>
                <x-finance-lookup type="projects" name="project_id" label="Progetto del cliente" :value="old('project_id', $invoice->project_id)" :text="\App\Models\Project::find(old('project_id', $invoice->project_id))?->name" :client="$invoice->client_id" />
            </fieldset>
            <fieldset class="finance-assignment-fields" x-show="assignment === 'campaign'" :disabled="assignment !== 'campaign'" @disabled($selectedAssignment !== 'campaign') x-cloak>
                <x-finance-lookup type="campaigns" name="marketing_campaign_id" label="Progetto marketing del cliente"
                    :value="$selectedCampaign" :text="\App\Models\MarketingCampaign::find($selectedCampaign)?->name" :client="$invoice->client_id" />
                <p class="u-text-meta u-mt-sm">Il progetto marketing sarà collegato all’intera fattura.</p>
            </fieldset>
            @can('create', \App\Models\Project::class)
            <fieldset class="finance-assignment-fields" x-show="assignment === 'new_project'" :disabled="assignment !== 'new_project'" @disabled($selectedAssignment !== 'new_project') x-cloak>
                <x-form-group label="Nome del nuovo progetto" name="new_project_name" required><input id="field-new-project-name" name="new_project_name" class="form-in" value="{{ old('new_project_name') }}" maxlength="255" required></x-form-group>
                <x-form-group label="Descrizione (facoltativa)" name="new_project_description"><textarea id="field-new-project-description" name="new_project_description" class="form-ta" rows="3" maxlength="5000">{{ old('new_project_description') }}</textarea></x-form-group>
                <p class="u-text-meta">Il progetto sarà creato per {{ $invoice->client?->name }}. Sarai inserito nel team del progetto.</p>
            </fieldset>
            @endcan
            @foreach(['domain' => 'Dominio', 'hosting' => 'Hosting'] as $type => $label)
            @php($serviceId = $selectedAssignment === $type ? old('hosting_service_id', $invoice->hosting_service_id) : null)
            <fieldset class="finance-assignment-fields" x-show="assignment === @js($type)" :disabled="assignment !== @js($type)" @disabled($selectedAssignment !== $type) x-cloak>
                <x-finance-lookup type="services" name="hosting_service_id" :id="'invoice-'.$type" :label="$label.' del cliente'" :service-type="$type"
                    :value="$serviceId" :text="\App\Models\HostingService::withTrashed()->find($serviceId)?->name" :client="$invoice->client_id" />
                <p class="u-text-meta u-mt-sm">Questo servizio sarà collegato all’intera fattura al posto del progetto.</p>
            </fieldset>
            @endforeach
            @if($invoice->marketing_campaign_id)
                <fieldset class="finance-assignment-fields"
                    x-show="assignment !== 'campaign' || String(campaign) !== @js((string) $invoice->marketing_campaign_id)"
                    :disabled="assignment === 'campaign' && String(campaign) === @js((string) $invoice->marketing_campaign_id)"
                    @disabled($selectedAssignment === 'campaign' && (string) $selectedCampaign === (string) $invoice->marketing_campaign_id) x-cloak>
                    <label class="u-flex u-gap-sm"><input type="checkbox" name="replace_campaign" value="1" required @checked(old('replace_campaign'))>Sostituisci il collegamento alla campagna {{ $invoice->marketingCampaign?->name }}</label>
                </fieldset>
            @endif
            <div class="modal-ft u-section-sep"><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">Salva collegamento</button></div>
        </form>
    </x-panel>
</x-app-layout>
