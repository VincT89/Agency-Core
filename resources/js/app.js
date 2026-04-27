import Alpine from 'alpinejs';
import './project-select.js';
import clientAutocomplete from './client-autocomplete.js';

window.Alpine = Alpine;

Alpine.data('clientAutocomplete', clientAutocomplete);

Alpine.start();

import { initBgCanvas } from './bg-canvas.js';
document.addEventListener('DOMContentLoaded', () => initBgCanvas('bg-canvas'));
