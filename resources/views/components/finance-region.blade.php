@props(['region'])
<div data-finance-region="{{ $region }}" x-data="financeRegion(@js($region))" :aria-busy="busy ? 'true' : 'false'"
    @submit="submit($event)" @click="follow($event)" @input="type($event)" @change="change($event)" @popstate.window="restore()"
    @finance-navigate.window="if ($event.detail.region === @js($region)) navigate($event.detail.url, 'push', $event.detail.focusId)"
    @finance-submit.window="if ($event.detail.region === @js($region)) submitForm($event.detail.form, false, $event.detail.focusId)">
    <p x-show="busy" x-cloak class="sr-only" role="status">Aggiornamento risultati…</p>
    <p x-show="error" x-cloak class="u-alert-error" role="alert" x-text="error"></p>
    <div x-ref="content">{{ $slot }}</div>
</div>
