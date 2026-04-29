import './project-select.js';
import clientAutocomplete from './client-autocomplete.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('clientAutocomplete', clientAutocomplete);
});

import { initBgCanvas } from './bg-canvas.js';
document.addEventListener('livewire:navigated', () => initBgCanvas('bg-canvas'));
// Fallback per non livewire pages
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.Livewire === 'undefined') {
        initBgCanvas('bg-canvas');
    }
});
