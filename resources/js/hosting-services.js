/**
 * Logica dedicata al modulo Hosting e Manutenzioni.
 * Gestisce l'estrazione sicura delle password e la copia.
 */
import { createIcons, icons } from 'lucide';

function initHostingServices() {

    function setIcon(btn, iconName, extraClass = '') {
        while(btn.firstChild) btn.removeChild(btn.firstChild);
        const i = document.createElement('i');
        i.setAttribute('data-lucide', iconName);
        i.className = 'u-icon-sm' + (extraClass ? ' ' + extraClass : '');
        btn.appendChild(i);
        createIcons({ icons, elements: [i] });
    }
    
    // Toggle Password
    document.querySelectorAll('.hosting-password-toggle:not(.js-bound)').forEach(btn => {
        btn.classList.add('js-bound');
        btn.addEventListener('click', async (e) => {
            const container = e.target.closest('.hosting-password-container');
            const valSpan = container.querySelector('.hosting-password-value');
            const status = container.querySelector('.hosting-password-status');
            const isHidden = valSpan.dataset.hidden === 'true';
            
            if (isHidden) {
                // Fai fetch dell'endpoint sicuro
                const hostingId = container.dataset.id;
                try {
                    setIcon(btn, 'loader');
                    btn.disabled = true;

                    const res = await fetch(`/hosting-services/${hostingId}/password`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!res.ok) throw new Error('Network error');

                    const data = await res.json();
                    
                    valSpan.textContent = data.password;
                    valSpan.dataset.hidden = 'false';
                    valSpan.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-pressed', 'true');
                    btn.setAttribute('aria-label', 'Nascondi password');
                    btn.setAttribute('title', 'Nascondi password');
                    status.textContent = 'Password visibile.';
                    setIcon(btn, 'eye-off');
                } catch (error) {
                    console.error('Errore nel fetch password:', error);
                    if (window.toast) toast('Errore caricamento password', 'error');
                    setIcon(btn, 'eye');
                } finally {
                    btn.disabled = false;
                }
            } else {
                // Nascondi
                valSpan.textContent = '••••••••';
                valSpan.dataset.hidden = 'true';
                valSpan.setAttribute('aria-hidden', 'true');
                btn.setAttribute('aria-pressed', 'false');
                btn.setAttribute('aria-label', 'Mostra password');
                btn.setAttribute('title', 'Mostra password');
                status.textContent = 'Password nascosta.';
                setIcon(btn, 'eye');
            }
        });
    });

    // Copia Password
    document.querySelectorAll('.hosting-password-copy:not(.js-bound)').forEach(btn => {
        btn.classList.add('js-bound');
        btn.addEventListener('click', async (e) => {
            const container = e.target.closest('.hosting-password-container');
            const valSpan = container.querySelector('.hosting-password-value');
            const status = container.querySelector('.hosting-password-status');
            const isHidden = valSpan.dataset.hidden === 'true';
            
            let passwordToCopy = valSpan.textContent;

            // Se è nascosta, la scarico
            if (isHidden) {
                const hostingId = container.dataset.id;
                try {
                    setIcon(btn, 'loader');
                    const res = await fetch(`/hosting-services/${hostingId}/password`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();
                    passwordToCopy = data.password;
                } catch (error) {
                    console.error('Errore nel fetch password per copia:', error);
                    if (window.toast) toast('Errore copia password', 'error');
                    setIcon(btn, 'copy');
                    return;
                }
            }

            navigator.clipboard.writeText(passwordToCopy).then(() => {
                setIcon(btn, 'check', 'u-text-teal');
                status.textContent = 'Password copiata.';
                setTimeout(() => {
                    setIcon(btn, 'copy');
                }, 2000);
            }).catch(() => {
                status.textContent = 'Impossibile copiare automaticamente la password.';
            });
        });
    });

    // Row Click per la tabella
    document.querySelectorAll('.js-row-link:not(.js-bound)').forEach(row => {
        row.classList.add('js-bound');
        if (!row.hasAttribute('tabindex')) row.tabIndex = 0;
        if (!row.hasAttribute('role')) row.setAttribute('role', 'link');

        const navigateToRow = () => {
            const href = row.dataset.href;
            if (!href) return;
            if (window.Livewire && window.Livewire.navigate) {
                window.Livewire.navigate(href);
            } else {
                window.location.href = href;
            }
        };

        row.addEventListener('click', (e) => {
            // Ignora se si clicca su link, bottoni, input, select o elementi con js-stop-propagation
            if (e.target.closest('a, button, input, select, .js-stop-propagation')) {
                return;
            }
            navigateToRow();
        });
        row.addEventListener('keydown', (e) => {
            if (e.target !== row || (e.key !== 'Enter' && e.key !== ' ')) return;
            e.preventDefault();
            navigateToRow();
        });
    });

    // Conferma eliminazione
    document.querySelectorAll('.js-confirm-delete:not(.js-bound)').forEach(form => {
        form.classList.add('js-bound');
        form.addEventListener('submit', (e) => {
            if (!confirm('Eliminare definitivamente questo elemento?')) {
                e.preventDefault();
            }
        });
    });
}

document.addEventListener('livewire:navigated', initHostingServices);
document.addEventListener('DOMContentLoaded', initHostingServices);
