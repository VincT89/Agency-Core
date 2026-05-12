@component('mail::message')
# È richiesta la tua approvazione

Ciao {{ $post->campaign->client->name }},

Il nostro team ha preparato un nuovo post per la campagna **{{ $post->campaign->name }}**. 
Ti invitiamo a visionarlo e a lasciarci il tuo feedback o la tua approvazione.

@php
    $versionImages = [];
    if ($post->currentVersion) {
        if (is_array($post->currentVersion->image_urls) && count($post->currentVersion->image_urls) > 0) {
            $versionImages = $post->currentVersion->image_urls;
        } elseif (! empty($post->currentVersion->image_url)) {
            $versionImages = [$post->currentVersion->image_url];
        }
    }
@endphp

@if(count($versionImages) > 0)
@foreach($versionImages as $vImg)
![Anteprima Post]({{ $vImg }})
@endforeach
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
