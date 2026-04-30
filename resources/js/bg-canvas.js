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

    const BLUE = [37, 99, 235];
    const RED  = [200, 16, 46];

    // Base bianco ghiaccio
    ctx.fillStyle = '#f6f8ff';
    ctx.fillRect(0, 0, W, H);

    // Alone blu in alto a sinistra
    const gb = ctx.createRadialGradient(W * 0.15, H * 0.05, 0, W * 0.15, H * 0.05, Math.max(W, H) * 0.75);
    gb.addColorStop(0,   `rgba(${BLUE}, .13)`);
    gb.addColorStop(0.4, `rgba(${BLUE}, .05)`);
    gb.addColorStop(1,   `rgba(${BLUE}, 0)`);
    ctx.fillStyle = gb;
    ctx.fillRect(0, 0, W, H);

    // Alone rosso in basso a destra
    const gr = ctx.createRadialGradient(W * 0.9, H, 0, W * 0.9, H, Math.max(W, H) * 0.7);
    gr.addColorStop(0,   `rgba(${RED}, .14)`);
    gr.addColorStop(0.4, `rgba(${RED}, .05)`);
    gr.addColorStop(1,   `rgba(${RED}, 0)`);
    ctx.fillStyle = gr;
    ctx.fillRect(0, 0, W, H);

    // Griglia sottile blu
    ctx.strokeStyle = 'rgba(37,99,235,.05)';
    ctx.lineWidth = 0.4;
    const s = 40;
    for (let x = 0; x <= W; x += s) {
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
    }
    for (let y = 0; y <= H; y += s) {
      ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
    }

    // Vignette bianca sui bordi — ammorbidisce
    const v = ctx.createRadialGradient(W * 0.5, H * 0.4, H * 0.1, W * 0.5, H * 0.4, Math.max(W, H));
    v.addColorStop(0, 'rgba(255,255,255,0)');
    v.addColorStop(1, 'rgba(255,255,255,.3)');
    ctx.fillStyle = v;
    ctx.fillRect(0, 0, W, H);
  }

  const resizeObserver = new ResizeObserver(draw);
  resizeObserver.observe(document.body);
  draw();
}
