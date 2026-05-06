import './project-select.js';
import clientAutocomplete from './client-autocomplete.js';
import { initShell } from './app-shell.js';
import { createIcons, icons } from 'lucide';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('clientAutocomplete', clientAutocomplete);
});

import { initBgCanvas } from './bg-canvas.js';
document.addEventListener('livewire:navigated', () => {
    initBgCanvas('bg-canvas');
    initShell();
    createIcons({ icons });
});

// Fallback per non livewire pages
document.addEventListener('DOMContentLoaded', () => {
    initShell();
    createIcons({ icons });
    if (typeof window.Livewire === 'undefined') {
        initBgCanvas('bg-canvas');
    }
});

document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => {
                createIcons({ icons });
            })
        })
    });
});
