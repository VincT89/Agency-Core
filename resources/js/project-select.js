// Popola dinamicamente il select progetto in base al cliente selezionato.

function initProjectSelect(clientSelectId, projectSelectId, currentProjectId = null) {
    const clientEl  = document.getElementById(clientSelectId);
    const projectEl = document.getElementById(projectSelectId);
    if (!clientEl || !projectEl) return;
    const helpEl = document.getElementById(`${projectSelectId}_help`);

    const setHelp = (message, isError = false) => {
        if (!helpEl) return;
        helpEl.textContent = message;
        helpEl.classList.toggle('form-err', isError);
    };

    const setDefaultOption = (label) => {
        while (projectEl.firstChild) projectEl.removeChild(projectEl.firstChild);
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = label;
        projectEl.appendChild(defaultOpt);
    };

    async function loadProjects(clientId) {
        if (!clientId) {
            setDefaultOption('Seleziona prima un cliente');
            projectEl.disabled = true;
            setHelp('Seleziona un cliente per caricare i progetti disponibili.');
            return;
        }

        projectEl.disabled = true;
        projectEl.setAttribute('aria-busy', 'true');
        setDefaultOption('Caricamento progetti...');
        setHelp('Caricamento progetti in corso.');

        try {
            const res  = await fetch(`/api/clients/${clientId}/projects`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setDefaultOption(data.length ? 'Seleziona progetto...' : 'Nessun progetto disponibile');
            projectEl.disabled = false;
            data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (currentProjectId && parseInt(p.id) === parseInt(currentProjectId)) {
                    opt.selected = true;
                }
                projectEl.appendChild(opt);
            });
            setHelp(data.length
                ? `${data.length} ${data.length === 1 ? 'progetto disponibile' : 'progetti disponibili'}.`
                : 'Il cliente non ha progetti: creane uno prima di registrare la fattura.', !data.length);
        } catch(e) {
            console.error('Errore caricamento progetti:', e);
            setDefaultOption('Progetti non disponibili');
            projectEl.disabled = true;
            setHelp('Non è stato possibile caricare i progetti. Riprova.', true);
        } finally {
            projectEl.removeAttribute('aria-busy');
        }
    }

    clientEl.addEventListener('change', () => {
        currentProjectId = null;
        loadProjects(clientEl.value);
    });

    // Se c'è già un cliente selezionato al caricamento della pagina
    loadProjects(clientEl.value);
}

function autoInitProjectSelects() {
    document.querySelectorAll('[data-client-select]:not(.js-bound)').forEach(clientSelect => {
        clientSelect.classList.add('js-bound');
        const projectSelectId = clientSelect.dataset.projectSelect;
        const currentProject = clientSelect.dataset.currentProject || null;
        if (projectSelectId) {
            initProjectSelect(clientSelect.id, projectSelectId, currentProject);
        }
    });
}

document.addEventListener('livewire:navigated', autoInitProjectSelects);
document.addEventListener('DOMContentLoaded', autoInitProjectSelects);

window.initProjectSelect = initProjectSelect;
