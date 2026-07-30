@component('mail::message')
# È richiesta la tua approvazione

Ciao {{ $data->clientName }},

Il nostro team ha preparato un nuovo post per la campagna **{{ $data->campaignName }}**. 
Ti invitiamo a visionarlo e a lasciarci il tuo feedback o la tua approvazione.

@if(count($data->previewUrls) > 0)
@foreach($data->previewUrls as $vImg)
![Anteprima Post]({{ $vImg }})
@endforeach
@endif

**Titolo:** {{ $data->postTitle }}

**Testo:**
{{ $data->postCaption }}

@component('mail::button', ['url' => $data->reviewUrl])
Vai alla Revisione
@endcomponent

Grazie,<br>
{{ config('app.name') }}
@endcomponent
