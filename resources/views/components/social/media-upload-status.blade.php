<div x-show="isUploadingLocalMedia" x-cloak class="cmp-media-upload-status" data-local-upload-status>
    <p class="cmp-post-help" role="status" aria-live="polite"
       x-text="localUploadProgress < 100 ? 'Caricamento file: ' + localUploadProgress + '%' : 'Trasferimento completato. Verifica dei file in corso...'">
    </p>
    <progress max="100" x-bind:value="localUploadProgress" aria-label="Avanzamento caricamento dei file"></progress>
    <p class="cmp-post-help">La comparsa delle anteprime non indica che il caricamento sia terminato. Attendi la conferma prima di salvare.</p>
</div>
