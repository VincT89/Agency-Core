<dl class="ticket-request-details">
    @foreach(['company_name' => 'Ragione sociale', 'reference_person' => 'Referente', 'email' => 'Email', 'phone' => 'Telefono', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale', 'billing_email' => 'Email fatturazione', 'pec' => 'PEC', 'sdi_code' => 'Codice SDI', 'address' => 'Indirizzo', 'city' => 'Comune', 'postal_code' => 'CAP', 'province' => 'Provincia', 'country' => 'Paese', 'country_code' => 'Codice Stato'] as $field => $label)
        <div><dt>{{ $label }}</dt><dd>{{ $client->$field ?: 'Non indicato' }}</dd></div>
    @endforeach
    <div><dt>Commerciale di riferimento</dt><dd>{{ $client->commercialUser?->name ?? 'Non assegnato' }}</dd></div>
    <div><dt>Stato cliente</dt><dd>{{ $client->status_label }}</dd></div>
    <div><dt>Registrato il</dt><dd>{{ $client->created_at->format('d/m/Y') }}</dd></div>
</dl>
