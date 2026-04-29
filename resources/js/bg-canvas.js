export function initBgCanvas(canvasId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || canvas.dataset.initialized) return;
  canvas.dataset.initialized = 'true';

  const ctx = canvas.getContext('2d');
  const ACCENT = [200, 16, 46];

  function draw() {
    const parent = canvas.parentElement;
    const W = canvas.width  = parent === document.body ? window.innerWidth  : parent.clientWidth;
    const H = canvas.height = parent === document.body ? window.innerHeight : parent.clientHeight;

    const cx = W * 0.5;
    const cy = H * 0.42;

    // Base calda marrone scuro
    ctx.fillStyle = 'rgb(40,18,10)';
    ctx.fillRect(0, 0, W, H);

    // Linee radiali sottili dal centro
    for (let a = 0; a < 32; a++) {
      const angle = (a / 32) * Math.PI * 2;
      const dist  = Math.max(W, H) * 0.9;
      const alpha = Math.max(0.012, 0.07 - Math.abs(Math.cos(angle * 2)) * 0.035);
      ctx.strokeStyle = `rgba(${ACCENT}, ${alpha})`;
      ctx.lineWidth   = 0.5;
      ctx.beginPath();
      ctx.moveTo(cx + Math.cos(angle) * 4, cy + Math.sin(angle) * 4);
      ctx.lineTo(cx + Math.cos(angle) * dist, cy + Math.sin(angle) * dist * 0.64);
      ctx.stroke();
    }

    // Core luminoso caldo — alone ambra + rosso
    const core = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(W, H) * 1.4);
    core.addColorStop(0,    'rgba(255,200,120,.32)');
    core.addColorStop(0.05, `rgba(${ACCENT},.55)`);
    core.addColorStop(0.18, `rgba(${ACCENT},.20)`);
    core.addColorStop(0.45, `rgba(${ACCENT},.07)`);
    core.addColorStop(1,    `rgba(${ACCENT},0)`);
    ctx.fillStyle = core;
    ctx.fillRect(0, 0, W, H);

    // Luce ambra diffusa
    const amb = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(W, H) * 0.9);
    amb.addColorStop(0,   'rgba(180,80,20,.12)');
    amb.addColorStop(0.4, 'rgba(120,40,10,.05)');
    amb.addColorStop(1,   'rgba(120,40,10,0)');
    ctx.fillStyle = amb;
    ctx.fillRect(0, 0, W, H);

    // Vignette morbida sui bordi
    const vig = ctx.createRadialGradient(cx, cy, Math.max(W, H) * 0.18, cx, cy, Math.max(W, H) * 0.92);
    vig.addColorStop(0, 'rgba(0,0,0,0)');
    vig.addColorStop(1, 'rgba(0,0,0,.55)');
    ctx.fillStyle = vig;
    ctx.fillRect(0, 0, W, H);
  }

  const resizeObserver = new ResizeObserver(draw);
  resizeObserver.observe(document.body);
  draw();
}
