import './project-select.js';
import clientAutocomplete from './client-autocomplete.js';
import './hosting-services.js';
import './confirm-forms.js';
import './ui-helpers.js';
import './projects/project-members.js';
import './shared-checklist.js';
import { initShell } from './app-shell.js';
import { createIcons, icons } from 'lucide';
import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;

window.Apex = {
    chart: {
        locales: [{
            "name": "it",
            "options": {
                "months": ["Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno", "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"],
                "shortMonths": ["Gen", "Feb", "Mar", "Apr", "Mag", "Giu", "Lug", "Ago", "Set", "Ott", "Nov", "Dic"],
                "days": ["Domenica", "Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato"],
                "shortDays": ["Dom", "Lun", "Mar", "Mer", "Gio", "Ven", "Sab"],
                "toolbar": {
                    "exportToSVG": "Scarica SVG",
                    "exportToPNG": "Scarica PNG",
                    "exportToCSV": "Scarica CSV",
                    "menu": "Menu",
                    "selection": "Selezione",
                    "selectionZoom": "Zoom Selezione",
                    "zoomIn": "Zoom In",
                    "zoomOut": "Zoom Out",
                    "pan": "Sposta",
                    "reset": "Reimposta Zoom"
                }
            }
        }],
        defaultLocale: "it"
    }
};
document.addEventListener('alpine:init', () => {
    window.Alpine.data('clientAutocomplete', clientAutocomplete);
});

import { initBgCanvas } from './bg-canvas.js';

document.addEventListener('livewire:navigating', () => {
    const root = document.querySelector('.page-transition-root');
    if (root) root.classList.add('is-navigating');
});

document.addEventListener('livewire:navigated', () => {
    const root = document.querySelector('.page-transition-root');
    if (root) root.classList.remove('is-navigating');
    
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
