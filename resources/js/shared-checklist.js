document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.js-checklist-toggle');

    if (!form) return;

    event.preventDefault();

    const row = form.closest('[data-checklist-item]');
    const button = row?.querySelector('[data-checklist-toggle-button]');
    const title = row?.querySelector('[data-checklist-title]');
    const completedBy = row?.querySelector('[data-checklist-completed-by]');
    const counter = document.querySelector('[data-checklist-counter]');

    if (!row || !button || !title) return;

    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        if (!response.ok) {
            throw new Error('Errore aggiornamento checklist');
        }

        const data = await response.json();

        button.textContent = data.is_completed ? '✓' : '○';

        title.classList.toggle('u-text-muted', data.is_completed);
        title.classList.toggle('task-checklist-completed', data.is_completed);
        title.classList.toggle('u-text-strong', !data.is_completed);

        if (completedBy) {
            completedBy.textContent = data.is_completed ? (data.completed_by ?? '') : '';
        }

        if (counter) {
            counter.textContent = `${data.done}/${data.total} completati`;
        }
    } catch (error) {
        console.error(error);
        alert('Non riesco ad aggiornare la checklist. Riprova.');
    } finally {
        button.disabled = false;
    }
});
