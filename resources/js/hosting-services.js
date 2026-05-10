/**
 * Logica dedicata al modulo Hosting e Manutenzioni.
 * Gestisce l'estrazione sicura delle password e la copia.
 */
import { createIcons, icons } from 'lucide';

document.addEventListener('DOMContentLoaded', () => {
    
    // Toggle Password
    document.querySelectorAll('.hosting-password-toggle').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const container = e.target.closest('.hosting-password-container');
            const valSpan = container.querySelector('.hosting-password-value');
            const isHidden = valSpan.dataset.hidden === 'true';
            
            if (isHidden) {
                // Fai fetch dell'endpoint sicuro
                const hostingId = container.dataset.id;
                try {
                    btn.innerHTML = '<i data-lucide="loader" class="u-icon-sm"></i>';
                    createIcons({ icons });
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
                    btn.innerHTML = '<i data-lucide="eye-off" class="u-icon-sm"></i>';
                    createIcons({ icons });
                } catch (error) {
                    console.error('Errore nel fetch password:', error);
                    if (window.toast) toast('Errore caricamento password', 'error');
                    btn.innerHTML = '<i data-lucide="eye" class="u-icon-sm"></i>';
                    createIcons({ icons });
                } finally {
                    btn.disabled = false;
                }
            } else {
                // Nascondi
                valSpan.textContent = '••••••••';
                valSpan.dataset.hidden = 'true';
                btn.innerHTML = '<i data-lucide="eye" class="u-icon-sm"></i>';
                createIcons({ icons });
            }
        });
    });

    // Copia Password
    document.querySelectorAll('.hosting-password-copy').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const container = e.target.closest('.hosting-password-container');
            const valSpan = container.querySelector('.hosting-password-value');
            const isHidden = valSpan.dataset.hidden === 'true';
            
            let passwordToCopy = valSpan.textContent;

            // Se è nascosta, la scarico
            if (isHidden) {
                const hostingId = container.dataset.id;
                try {
                    btn.innerHTML = '<i data-lucide="loader" class="u-icon-sm"></i>';
                    createIcons({ icons });
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
                    btn.innerHTML = '<i data-lucide="copy" class="u-icon-sm"></i>';
                    createIcons({ icons });
                    return;
                }
            }

            navigator.clipboard.writeText(passwordToCopy).then(() => {
                btn.innerHTML = '<i data-lucide="check" class="u-icon-sm u-text-teal"></i>';
                createIcons({ icons });
                setTimeout(() => {
                    btn.innerHTML = '<i data-lucide="copy" class="u-icon-sm"></i>';
                    createIcons({ icons });
                }, 2000);
            });
        });
    });

    // Row Click per la tabella
    document.querySelectorAll('.js-row-link').forEach(row => {
        row.addEventListener('click', (e) => {
            // Ignora se si clicca su link, bottoni, input, select o elementi con js-stop-propagation
            if (e.target.closest('a, button, input, select, .js-stop-propagation')) {
                return;
            }
            const href = row.dataset.href;
            if (href) {
                window.location = href;
            }
        });
    });

    // Conferma eliminazione
    document.querySelectorAll('.js-confirm-delete').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!confirm('Eliminare definitivamente questo elemento?')) {
                e.preventDefault();
            }
        });
    });
});
