/**
 * HORIZON AI — Cinematic Discovery Engine
 * Particle FX + Scroll Intelligence
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

  // Scene: Deep Space / Aurora Blend
  const stars = Array.from({ length: 150 }, () => ({
    x: rand(0, W), y: rand(0, H),
    r: rand(0.5, 1.8),
    a: rand(0.2, 0.8),
    twink: rand(0, Math.PI * 2),
    twinkSpeed: rand(0.01, 0.03),
  }));

  function drawScene() {
    stars.forEach(s => {
      s.twink += s.twinkSpeed;
      const a = s.a * (0.6 + 0.4 * Math.sin(s.twink));
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(215,225,255,${a})`;
      ctx.fill();
    });
  }

  function tick() {
    requestAnimationFrame(tick);
    mouseX = lerp(mouseX, targetMX, 0.05);
    mouseY = lerp(mouseY, targetMY, 0.05);
    ctx.clearRect(0, 0, W, H);
    drawScene();
  }
  tick();

  // Scroll Intelligence: Navigation Highlights
  const sections = document.querySelectorAll('section.panel');
  const navLinks = document.querySelectorAll('.nav-link[data-section]');

  const observerOptions = {
    root: null,
    threshold: 0.6
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(link => {
          link.classList.toggle('active', link.getAttribute('href').includes(id));
        });
      }
    });
  }, observerOptions);

  sections.forEach(section => observer.observe(section));

  // Smooth Scroll
  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const targetId = link.getAttribute('href').split('#')[1];
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        window.scrollTo({
          top: targetEl.offsetTop,
          behavior: 'smooth'
        });
      }
    });
  });

  // Hero Stats Entry Animation
  const stats = document.querySelectorAll('.stat-item');
  stats.forEach((s, i) => {
     s.style.opacity = '0';
     s.style.transform = 'translateY(10px)';
     setTimeout(() => {
        s.style.transition = 'all 1s var(--ease)';
        s.style.opacity = '1';
        s.style.transform = 'translateY(0)';
     }, 400 + (i * 200));
  });

})();
