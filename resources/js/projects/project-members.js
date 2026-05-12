document.addEventListener('change', (event) => {
    if (event.target.id !== 'project-department-filter') {
        return;
    }

    const selectedDepartment = event.target.value;

    document.querySelectorAll('[data-project-member-option]').forEach((option) => {
        const department = option.dataset.department || '';
        const visible = !selectedDepartment || department === selectedDepartment;

        option.toggleAttribute('hidden', !visible);
    });
});
