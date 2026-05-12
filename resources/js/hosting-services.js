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
                setTimeout(() => {
                    setIcon(btn, 'copy');
                }, 2000);
            });
        });
    });

    // Row Click per la tabella
    document.querySelectorAll('.js-row-link:not(.js-bound)').forEach(row => {
        row.classList.add('js-bound');
        row.addEventListener('click', (e) => {
            // Ignora se si clicca su link, bottoni, input, select o elementi con js-stop-propagation
            if (e.target.closest('a, button, input, select, .js-stop-propagation')) {
                return;
            }
            const href = row.dataset.href;
            if (href) {
                if (window.Livewire && window.Livewire.navigate) {
                    window.Livewire.navigate(href);
                } else {
                    window.location.href = href;
                }
            }
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
