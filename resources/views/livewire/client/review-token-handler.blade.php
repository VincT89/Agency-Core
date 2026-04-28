<div>
    @if($reviewable instanceof \App\Models\EditorialPlan)
        <livewire:client.editorial-plan-review :plan="$reviewable" :token="$token" />
    @elseif($reviewable instanceof \App\Models\SocialPost)
        <livewire:client.social.social-post-review :token="$token" />
    @else
        <div style="padding: 40px; text-align: center; color: var(--text2);">
            <h2>Tipo di risorsa non supportato.</h2>
        </div>
    @endif
</div>
