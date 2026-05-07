document.addEventListener('DOMContentLoaded', () => {
    initConfirmForms();
});

document.addEventListener('livewire:navigated', () => {
    initConfirmForms();
});

function initConfirmForms() {
    const forms = document.querySelectorAll('.js-confirm-form');
    forms.forEach(form => {
        // Prevent multiple listeners
        if (form.dataset.confirmInitialized) return;
        form.dataset.confirmInitialized = 'true';

        form.addEventListener('submit', function(e) {
            const message = form.dataset.confirmMessage || 'Sei sicuro di voler procedere con questa operazione?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}
