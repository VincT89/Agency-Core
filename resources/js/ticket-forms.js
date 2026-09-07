function initTicketForms() {
    document.querySelectorAll('[data-ticket-form]:not([data-ticket-bound])').forEach(form => {
        form.dataset.ticketBound = 'true';
        const type = form.querySelector('[data-ticket-type]');
        const project = form.querySelector('[name="project_id"]');
        const sync = () => {
            project.required = type.value !== 'quote';
            project.setAttribute('aria-required', String(project.required));
        };
        type.addEventListener('change', sync);
        sync();
    });
    document.querySelectorAll('[data-department-assignee]:not([data-assignment-bound])').forEach(group => {
        group.dataset.assignmentBound = 'true';
        const department = group.querySelector('[data-assignment-department]');
        const users = group.querySelector('[data-assignment-user]');
        const project = group.closest('form').querySelector('[name="project_id"]');
        const help = group.querySelector('[role="status"]');
        const initialUser = users.value;
        const sync = (initial = false) => {
            let available = 0;
            [...users.options].forEach(option => {
                if (!option.value) return;
                const projects = (option.dataset.projects || '').split(',');
                const inScope = !project?.value || option.dataset.global === '1' || projects.includes(project.value);
                const visible = (option.dataset.department === department.value && inScope)
                    || (initial && option.value === initialUser);
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) available++;
            });
            if (users.selectedOptions[0]?.disabled) users.value = '';
            users.disabled = !department.value;
            help.textContent = !department.value ? 'Scegli il reparto per selezionare il referente.'
                : available ? '' : 'Nessun referente disponibile per questo progetto e reparto.';
        };
        department.addEventListener('change', () => { users.value = ''; sync(); });
        project?.addEventListener('change', () => sync());
        sync(true);
    });
}
document.addEventListener('DOMContentLoaded', initTicketForms);
document.addEventListener('livewire:navigated', initTicketForms);
