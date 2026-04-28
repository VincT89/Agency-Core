/**
 * Sodano Consulting — Generative Background Canvas
 * Pattern: griglia di croci rosse + linee diagonali + alone radiale
 * Zero dipendenze. Risponde al resize della finestra.
 */
export function initBgCanvas(canvasId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || canvas.dataset.initialized) return;
  canvas.dataset.initialized = 'true';

  const ctx = canvas.getContext('2d');
  const ACCENT = [200, 16, 46]; // --accent in RGB (#c8102e)

  function draw() {
    const parent = canvas.parentElement;
    const W = canvas.width  = parent === document.body ? window.innerWidth : parent.clientWidth;
    const H = canvas.height = parent === document.body ? window.innerHeight : parent.clientHeight;

    // Background base
    ctx.fillStyle = '#140a0c';
    ctx.fillRect(0, 0, W, H);

    // Punto di luce: in alto a destra, zona topbar
    const cx = W * 0.72;
    const cy = H * 0.15;
    const maxDist = Math.sqrt(W * W + H * H) * 0.85;

    // Griglia di croci rosse che si dissolvono
    const step = 40;
    for (let r = 0; r <= Math.ceil(H / step) + 1; r++) {
      for (let c = 0; c <= Math.ceil(W / step) + 1; c++) {
        const x = c * step;
        const y = r * step;
        const dist = Math.sqrt((x - cx) ** 2 + (y - cy) ** 2);
        const alpha = Math.max(0, (1 - dist / maxDist) * 0.35);
        if (alpha < 0.004) continue;
        ctx.strokeStyle = `rgba(${ACCENT},${alpha})`;
        ctx.lineWidth = 0.6;
        ctx.beginPath();
        ctx.moveTo(x - 5, y); ctx.lineTo(x + 5, y);
        ctx.moveTo(x, y - 5); ctx.lineTo(x, y + 5);
        ctx.stroke();
      }
    }

    // Linee diagonali sfumate
    for (let i = 0; i < 10; i++) {
      const alpha = Math.max(0, 0.065 - i * 0.006);
      ctx.strokeStyle = `rgba(${ACCENT},${alpha})`;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(W * 0.52 + i * 32, 0);
      ctx.lineTo(W * 0.52 + i * 32 - H * 0.28, H);
      ctx.stroke();
    }

    // Alone radiale
    const gradientRadius = Math.max(W, H) * 0.85;
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, gradientRadius);
    g.addColorStop(0, `rgba(${ACCENT},0.22)`);
    g.addColorStop(1, `rgba(${ACCENT},0)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
  }

  const resizeObserver = new ResizeObserver(draw);
  resizeObserver.observe(document.body);
  draw();
}
