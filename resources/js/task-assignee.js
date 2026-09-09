function initTaskAssignees() {
    document.querySelectorAll('[data-task-assignee]:not([data-assignee-bound])').forEach(select => {
        const project = select.closest('form').querySelector('[name="project_id"]');
        const help = select.closest('[data-form-group]')?.querySelector('[data-task-assignee-help]');
        if (!project || !help) return;
        select.dataset.assigneeBound = 'true';

        const sync = () => {
            let available = 0;
            let invalidSelection = false;
            [...select.options].forEach(option => {
                if (!option.value) return;
                const canReceive = option.dataset.eligible === '1' && Boolean(project.value)
                    && (option.dataset.global === '1' || (option.dataset.projects || '').split(',').includes(project.value));
                option.disabled = !canReceive;
                option.hidden = !canReceive && !option.selected;
                if (canReceive) available++;
                if (option.selected && !canReceive) invalidSelection = true;
            });

            const error = 'L’assegnatario selezionato non può accedere a questa task. Verifica ruolo, stato e team di commessa oppure scegli un altro destinatario.';
            select.setCustomValidity(invalidSelection ? error : '');
            help.textContent = invalidSelection ? error
                : !project.value ? 'Seleziona un progetto per scegliere l’assegnatario.'
                : !available ? 'Nessun utente attivo abilitato a questo progetto. Aggiungi il destinatario al team di commessa prima di assegnare la task.'
                : 'Puoi scegliere chi ha accesso alle task di questo progetto. Se manca un collega, aggiungilo al team di commessa.';
        };

        project.addEventListener('change', sync);
        select.addEventListener('change', sync);
        sync();
    });
}

document.addEventListener('DOMContentLoaded', initTaskAssignees);
document.addEventListener('livewire:navigated', initTaskAssignees);
