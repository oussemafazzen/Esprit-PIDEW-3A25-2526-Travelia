/**
 * HORIZON AI — Canvas FX + Content Engine
 */
(function () {
  'use strict';

  const canvas = document.getElementById('live-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W = canvas.width = window.innerWidth;
  let H = canvas.height = window.innerHeight;

  window.addEventListener('resize', () => {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
  });

  let mouseX = W / 2, mouseY = H / 2;
  let targetMX = W / 2, targetMY = H / 2;
  window.addEventListener('mousemove', e => { targetMX = e.clientX; targetMY = e.clientY; });

  function lerp(a, b, t) { return a + (b - a) * t; }
  function rand(a, b) { return a + Math.random() * (b - a); }

  // Scene 0: Aurora
  const aurora = {
    bands: Array.from({ length: 7 }, (_, i) => ({
      phase:  rand(0, Math.PI * 2),
      speed:  rand(0.002, 0.007),
      y:      H * rand(0.15, 0.6),
      amp:    rand(60, 140),
      thick:  rand(80, 200),
      hue:    [160, 180, 200, 220, 140][i % 5],
      sat:    rand(60, 90),
    })),
    stars: Array.from({ length: 150 }, () => ({
      x: rand(0, W), y: rand(0, H),
      r: rand(0.4, 1.8),
      a: rand(0.3, 1),
      twink: rand(0, Math.PI * 2),
      twinkSpeed: rand(0.005, 0.025),
    })),
  };

  function drawAurora() {
    aurora.stars.forEach(s => {
      s.twink += s.twinkSpeed;
      const a = s.a * (0.5 + 0.5 * Math.sin(s.twink));
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(220,230,255,${a})`;
      ctx.fill();
    });

    aurora.bands.forEach(b => {
      b.phase += b.speed;
      const gradient = ctx.createLinearGradient(0, b.y - b.thick, 0, b.y + b.thick);
      const alpha = 0.07 + 0.05 * Math.sin(b.phase * 1.3);
      gradient.addColorStop(0, 'transparent');
      gradient.addColorStop(0.3, `hsla(${b.hue},${b.sat}%,60%,${alpha})`);
      gradient.addColorStop(0.5, `hsla(${b.hue + 20},${b.sat}%,70%,${alpha * 1.6})`);
      gradient.addColorStop(1, 'transparent');

      ctx.beginPath();
      ctx.moveTo(0, b.y);
      for (let x = 0; x <= W; x += 20) {
        const wave = Math.sin(x * 0.004 + b.phase) * b.amp;
        ctx.lineTo(x, b.y + wave);
      }
      ctx.lineTo(W, b.y + b.thick * 2);
      ctx.lineTo(0, b.y + b.thick * 2);
      ctx.fillStyle = gradient;
      ctx.fill();
    });
  }

  let activeScene = 0;
  function tick() {
    requestAnimationFrame(tick);
    mouseX = lerp(mouseX, targetMX, 0.05);
    mouseY = lerp(mouseY, targetMY, 0.05);
    ctx.clearRect(0, 0, W, H);
    drawAurora();
  }
  tick();

  // Scroll to sections
  document.querySelectorAll('.nav-link[data-section]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const targetId = link.getAttribute('href').substring(1);
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        targetEl.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

})();
