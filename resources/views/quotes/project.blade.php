<x-app-layout title="Crea progetto dall’offerta">
    <x-page-header><x-slot:title>Crea progetto dall’offerta accettata</x-slot:title></x-page-header>
    <x-panel padded>
        <p class="u-mb-md">Cliente: <strong>{{ $quote->client->name }}</strong>. Offerta #{{ $quote->id }}.</p>
        @if($errors->any())<div role="alert" class="ca-error-text">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('quotes.project.store', $quote) }}">
            @csrf
            <input name="client_id" type="hidden" value="{{ $quote->client_id }}">
            <x-form-group label="Nome progetto" name="name" required><input name="name" class="form-in" maxlength="255" value="{{ old('name', $quote->title) }}" required></x-form-group>
            <x-form-group label="Codice progetto" name="code"><input name="code" class="form-in" maxlength="50" value="{{ old('code') }}"></x-form-group>
            <fieldset class="commercial-services"><legend>Team di commessa</legend>
                <div class="commercial-members" data-required-checkbox-group data-required-message="Seleziona almeno un membro del team.">
                    @foreach($users as $member)<label><input type="checkbox" name="members[]" value="{{ $member->id }}" @checked(in_array($member->id, (array) old('members', [])))> {{ $member->name }} ({{ $member->role->label() }})</label>@endforeach
                </div>
            </fieldset>
            <div class="form-row">
                <x-form-group label="Data di avvio" name="start_date"><input type="date" name="start_date" class="form-in" value="{{ old('start_date') }}"></x-form-group>
                <x-form-group label="Data prevista di fine" name="end_date"><input type="date" name="end_date" class="form-in" value="{{ old('end_date') }}"></x-form-group>
            </div>
            <x-form-group label="Stato" name="status"><select name="status" class="form-sel"><option value="active" @selected(old('status') === 'active')>Attivo</option><option value="on_hold" @selected(old('status') === 'on_hold')>In pausa</option></select></x-form-group>
            <x-form-group label="Servizi e descrizione del progetto" name="description">
                <textarea name="description" class="form-ta" rows="7">{{ old('description', $quote->items->map(fn ($item) => $item->name.($item->description ? ': '.$item->description : ''))->implode("\n\n")) }}</textarea>
            </x-form-group>
            <div class="modal-ft u-section-sep commercial-actions"><a href="{{ route('quotes.show', $quote) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">Crea e collega progetto</button></div>
        </form>
    </x-panel>
</x-app-layout>
