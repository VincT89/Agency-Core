import Alpine from 'alpinejs';
import './project-select.js';
import clientAutocomplete from './client-autocomplete.js';

window.Alpine = Alpine;

Alpine.data('clientAutocomplete', clientAutocomplete);

Alpine.start();

import { initBgCanvas } from './bg-canvas.js';
document.addEventListener('livewire:navigated', () => initBgCanvas('bg-canvas'));
// Fallback for non-livewire pages
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.Livewire === 'undefined') {
        initBgCanvas('bg-canvas');
    }
});
