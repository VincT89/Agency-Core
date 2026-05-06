// resources/js/app-shell.js

export function initShell() {

  // --- Sidebar state con persistenza localStorage ---
  const SIDEBAR_KEY = 'sodano_sidebar';
  window.sidebarOpen = localStorage.getItem(SIDEBAR_KEY) !== 'closed';
  const shell = document.getElementById('shell');
  if (shell) shell.classList.toggle('expanded', window.sidebarOpen);

  window.toggleSidebar = function () {
    window.sidebarOpen = !window.sidebarOpen;
    localStorage.setItem(SIDEBAR_KEY, window.sidebarOpen ? 'open' : 'closed');
    if (shell) shell.classList.toggle('expanded', window.sidebarOpen);
    const icon = document.getElementById('sidebar-toggle-icon');
    if (icon) {
      icon.innerHTML = window.sidebarOpen
        ? '<path d="m15 18-6-6 6-6"/>'
        : '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>';
    }
  };

  // --- Toast system tipizzato ---
  window.toast = function (msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.dataset.type = type; // permette stili CSS: #toast[data-type="error"] { ... }
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2600);
  };

  // --- Listener globali: solo una volta ---
  if (window._shellListenersAdded) return;
  window._shellListenersAdded = true;

  // --- Chiusura overlay (.overlay.open) con Escape ---
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open'));
    }
  });

  // --- Chiusura overlay cliccando fuori ---
  document.addEventListener('click', e => {
    document.querySelectorAll('.overlay.open').forEach(o => {
      if (e.target === o) o.classList.remove('open');
    });
  });
}
