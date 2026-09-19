let nextProjectSearchId = 0;

function initProjectSearch() {
    document.querySelectorAll('select[data-project-search]:not([data-search-bound])').forEach(select => {
        select.dataset.searchBound = 'true';
        const id = `project-search-${++nextProjectSearchId}`;
        const options = [...select.options].filter(option => option.value).map(option => ({
            value: option.value,
            label: option.textContent.trim().replace(/\s+/g, ' '),
        }));
        const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('it');
        const wrapper = document.createElement('div');
        wrapper.className = 'project-search';
        const input = document.createElement('input');
        input.type = 'search';
        input.id = id;
        input.className = `form-in ${select.classList.contains('is-invalid') ? 'is-invalid' : ''}`;
        input.placeholder = 'Cerca progetto o cliente...';
        input.autocomplete = 'off';
        input.required = select.required;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', `${id}-options`);
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-describedby', `${id}-help`);
        const list = document.createElement('div');
        list.id = `${id}-options`;
        list.className = 'project-search-options';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', 'Progetti disponibili');
        list.hidden = true;
        const help = document.createElement('div');
        help.id = `${id}-help`;
        help.className = 'project-search-help';
        help.setAttribute('role', 'status');
        help.textContent = 'Cerca per nome progetto o cliente e seleziona un risultato.';
        wrapper.append(input, list, help);
        select.before(wrapper);
        select.hidden = true;
        select.required = false;
        const label = select.closest('[data-form-group]')?.querySelector('label');
        if (label) label.htmlFor = id;
        let matches = [];
        let active = -1;

        const sync = () => {
            input.value = options.find(option => option.value === select.value)?.label || '';
            input.setCustomValidity(input.required && !select.value ? 'Seleziona un progetto dai risultati.' : '');
        };
        const close = () => {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        };
        const choose = option => {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            close();
            input.focus();
            close();
        };
        const render = (query = '') => {
            const found = options.filter(option => normalize(option.label).includes(normalize(query)));
            matches = found.slice(0, 50);
            active = -1;
            list.replaceChildren();
            matches.forEach((option, index) => {
                const item = document.createElement('div');
                item.id = `${id}-option-${index}`;
                item.className = 'project-search-option';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(option.value === select.value));
                item.textContent = option.label;
                item.addEventListener('mousedown', event => event.preventDefault());
                item.addEventListener('click', () => choose(option));
                list.append(item);
            });
            help.textContent = !found.length ? 'Nessun progetto trovato.'
                : found.length > 50 ? `${found.length} progetti trovati. Affina la ricerca per vedere gli altri risultati.`
                : `${found.length} ${found.length === 1 ? 'progetto trovato' : 'progetti trovati'}.`;
            list.hidden = matches.length === 0;
            input.setAttribute('aria-expanded', String(matches.length > 0));
            input.removeAttribute('aria-activedescendant');
        };
        input.addEventListener('focus', () => render(select.value ? '' : input.value));
        input.addEventListener('input', () => {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.setCustomValidity(input.required ? 'Seleziona un progetto dai risultati.' : '');
            render(input.value);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'Enter' && !list.hidden) {
                event.preventDefault();
                if (active >= 0) choose(matches[active]);
                else if (matches.length === 1) choose(matches[0]);
                return;
            }
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            if (list.hidden) render(select.value ? '' : input.value);
            if (!matches.length) return;
            active = active < 0
                ? (event.key === 'ArrowDown' ? 0 : matches.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
            [...list.children].forEach((item, index) => item.classList.toggle('is-active', index === active));
            const item = list.children[active];
            input.setAttribute('aria-activedescendant', item.id);
            item.scrollIntoView({ block: 'nearest' });
        });
        wrapper.addEventListener('focusout', () => queueMicrotask(() => {
            if (!wrapper.contains(document.activeElement)) close();
        }));
        select.form?.addEventListener('reset', () => setTimeout(() => { sync(); close(); }, 0));
        sync();
    });
}

document.addEventListener('DOMContentLoaded', initProjectSearch);
document.addEventListener('livewire:navigated', initProjectSearch);
