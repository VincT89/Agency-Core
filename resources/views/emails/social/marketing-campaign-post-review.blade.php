@component('mail::message')
# È richiesta la tua approvazione

Ciao {{ $post->campaign->client->name }},

Il nostro team ha preparato un nuovo post per la campagna **{{ $post->campaign->name }}**. 
Ti invitiamo a visionarlo e a lasciarci il tuo feedback o la tua approvazione.

@if($post->currentVersion?->image_url)
![Anteprima Post]({{ $post->currentVersion->image_url }})
@endif

**Titolo:** {{ $post->currentVersion?->title ?: 'N/A' }}

**Testo:**
{{ $post->currentVersion?->caption ?: 'N/A' }}

@component('mail::button', ['url' => route('public.marketing-campaign-posts.review', ['token' => $token->token])])
Vai alla Revisione
@endcomponent

Grazie,<br>
{{ config('app.name') }}
@endcomponent
