// Popola dinamicamente il select progetto in base al cliente selezionato.

function initProjectSelect(clientSelectId, projectSelectId, currentProjectId = null) {
    const clientEl  = document.getElementById(clientSelectId);
    const projectEl = document.getElementById(projectSelectId);
    if (!clientEl || !projectEl) return;

    async function loadProjects(clientId) {
        if (!clientId) {
            projectEl.innerHTML = '<option value="">Nessun progetto...</option>';
            return;
        }
        try {
            const res  = await fetch(`/api/clients/${clientId}/projects`);
            const data = await res.json();
            projectEl.innerHTML = '<option value="">Nessun progetto...</option>';
            data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (currentProjectId && parseInt(p.id) === parseInt(currentProjectId)) {
                    opt.selected = true;
                }
                projectEl.appendChild(opt);
            });
        } catch(e) {
            console.error('Errore caricamento progetti:', e);
        }
    }

    clientEl.addEventListener('change', () => {
        loadProjects(clientEl.value);
    });

    // Se c'è già un cliente selezionato al caricamento della pagina
    if (clientEl.value) {
        loadProjects(clientEl.value);
    }
}

// Auto-inizializzazione
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-client-select]').forEach(clientSelect => {
        const projectSelectId = clientSelect.dataset.projectSelect;
        const currentProject = clientSelect.dataset.currentProject || null;
        if (projectSelectId) {
            initProjectSelect(clientSelect.id, projectSelectId, currentProject);
        }
    });
});

window.initProjectSelect = initProjectSelect;
