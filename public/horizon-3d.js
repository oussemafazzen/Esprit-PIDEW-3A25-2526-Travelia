/**
 * HORIZON AI — Canvas FX + Content Engine
 * Per-section canvas effects: aurora, bokeh, light streaks, constellations, nebula
 */
(function () {
  'use strict';

  // ── Canvas Setup ──
  const canvas = document.getElementById('live-canvas');
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
  function randInt(a, b) { return Math.floor(rand(a, b)); }

  // ─────────────────────────────────────────────
  //  SCENE 0 — AURORA BOREALIS (Hero)
  // ─────────────────────────────────────────────
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
    stars: Array.from({ length: 220 }, () => ({
      x: rand(0, W), y: rand(0, H),
      r: rand(0.4, 1.8),
      a: rand(0.3, 1),
      twink: rand(0, Math.PI * 2),
      twinkSpeed: rand(0.005, 0.025),
    })),
  };

  function drawAurora(t) {
    // Stars
    aurora.stars.forEach(s => {
      s.twink += s.twinkSpeed;
      const a = s.a * (0.5 + 0.5 * Math.sin(s.twink));
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(220,230,255,${a})`;
      ctx.fill();
    });

    // Aurora curtains
    aurora.bands.forEach(b => {
      b.phase += b.speed;
      const gradient = ctx.createLinearGradient(0, b.y - b.thick, 0, b.y + b.thick);
      const alpha = 0.07 + 0.05 * Math.sin(b.phase * 1.3);
      gradient.addColorStop(0, 'transparent');
      gradient.addColorStop(0.3, `hsla(${b.hue},${b.sat}%,60%,${alpha})`);
      gradient.addColorStop(0.5, `hsla(${b.hue + 20},${b.sat}%,70%,${alpha * 1.6})`);
      gradient.addColorStop(0.7, `hsla(${b.hue},${b.sat}%,60%,${alpha})`);
      gradient.addColorStop(1, 'transparent');

      ctx.beginPath();
      ctx.moveTo(0, b.y);
      for (let x = 0; x <= W; x += 20) {
        const wave = Math.sin(x * 0.004 + b.phase) * b.amp
                   + Math.sin(x * 0.009 + b.phase * 0.7) * (b.amp * 0.4);
        ctx.lineTo(x, b.y + wave);
      }
      ctx.lineTo(W, b.y + b.thick * 2);
      ctx.lineTo(0, b.y + b.thick * 2);
      ctx.closePath();
      ctx.fillStyle = gradient;
      ctx.fill();
    });

    // Subtle mouse parallax glow
    const px = mouseX, py = mouseY;
    const radGlow = ctx.createRadialGradient(px, py, 0, px, py, 250);
    radGlow.addColorStop(0, 'rgba(99,102,241,0.04)');
    radGlow.addColorStop(1, 'transparent');
    ctx.fillStyle = radGlow;
    ctx.fillRect(0, 0, W, H);
  }

  // ─────────────────────────────────────────────
  //  SCENE 1 — WARM GOLDEN BOKEH (Hotels)
  // ─────────────────────────────────────────────
  const bokeh = {
    particles: Array.from({ length: 60 }, () => ({
      x: rand(0, W), y: rand(0, H),
      r: rand(6, 45),
      vx: rand(-0.2, 0.2), vy: rand(-0.35, -0.05),
      a: rand(0.04, 0.18),
      hue: rand(28, 50),
      phase: rand(0, Math.PI * 2),
    })),
  };

  function drawBokeh(t) {
    bokeh.particles.forEach(p => {
      p.phase += 0.012;
      p.x += p.vx + Math.sin(p.phase * 0.5) * 0.15;
      p.y += p.vy;
      if (p.y < -p.r * 2) { p.y = H + p.r; p.x = rand(0, W); }

      const a = p.a * (0.6 + 0.4 * Math.sin(p.phase));
      const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
      grad.addColorStop(0, `hsla(${p.hue},90%,75%,${a})`);
      grad.addColorStop(1, 'transparent');
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = grad;
      ctx.fill();
    });

    // Warm vignette from bottom
    const vig = ctx.createLinearGradient(0, H * 0.5, 0, H);
    vig.addColorStop(0, 'transparent');
    vig.addColorStop(1, 'rgba(40,15,5,0.35)');
    ctx.fillStyle = vig;
    ctx.fillRect(0, 0, W, H);
  }

  // ─────────────────────────────────────────────
  //  SCENE 2 — LIGHT STREAKS / FLIGHT TRAILS
  // ─────────────────────────────────────────────
  const streaks = {
    trails: Array.from({ length: 14 }, () => {
      const angle = rand(-0.15, 0.1);
      return {
        x: rand(-200, W + 200), y: rand(H * 0.1, H * 0.8),
        vx: rand(2, 5), vy: Math.tan(angle) * rand(2, 5),
        len: rand(120, 380),
        a: rand(0.06, 0.18),
        width: rand(0.5, 2),
        phase: rand(0, Math.PI * 2),
        hue: rand(195, 230),
      };
    }),
    // Engine glow pulses
    pulses: Array.from({ length: 4 }, (_, i) => ({
      x: rand(W * 0.2, W * 0.8),
      y: rand(H * 0.2, H * 0.7),
      r: 0, maxR: rand(50, 120),
      a: rand(0.05, 0.12),
      speed: rand(0.5, 1.2),
    })),
  };

  function drawStreaks(t) {
    // Moving light trails
    streaks.trails.forEach(s => {
      s.phase += 0.015;
      s.x += s.vx;
      s.y += s.vy;
      if (s.x > W + 400) { s.x = -400; s.y = rand(H * 0.1, H * 0.85); }

      const tailX = s.x - s.vx * (s.len / s.vx);
      const tailY = s.y - s.vy * (s.len / s.vx);
      const opacity = s.a * (0.5 + 0.5 * Math.sin(s.phase));

      const grad = ctx.createLinearGradient(tailX, tailY, s.x, s.y);
      grad.addColorStop(0, 'transparent');
      grad.addColorStop(0.7, `hsla(${s.hue},85%,80%,${opacity * 0.5})`);
      grad.addColorStop(1, `hsla(${s.hue},95%,95%,${opacity})`);

      ctx.beginPath();
      ctx.moveTo(tailX, tailY);
      ctx.lineTo(s.x, s.y);
      ctx.strokeStyle = grad;
      ctx.lineWidth = s.width;
      ctx.stroke();

      // Head dot
      const gHead = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, 6);
      gHead.addColorStop(0, `hsla(${s.hue},100%,98%,${opacity})`);
      gHead.addColorStop(1, 'transparent');
      ctx.beginPath();
      ctx.arc(s.x, s.y, 6, 0, Math.PI * 2);
      ctx.fillStyle = gHead;
      ctx.fill();
    });

    // Expanding rings (engine pulse)
    streaks.pulses.forEach(p => {
      p.r += p.speed;
      if (p.r > p.maxR) { p.r = 0; }
      const f = p.r / p.maxR;
      const ringA = p.a * (1 - f);
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(150,200,255,${ringA})`;
      ctx.lineWidth = 1;
      ctx.stroke();
    });
  }

  // ─────────────────────────────────────────────
  //  SCENE 3 — CONSTELLATION / STARMAP (Activities)
  // ─────────────────────────────────────────────
  const constellations = {
    nodes: Array.from({ length: 80 }, () => ({
      x: rand(0, W), y: rand(0, H),
      r: rand(0.8, 2.5),
      vx: rand(-0.06, 0.06), vy: rand(-0.06, 0.06),
      a: rand(0.4, 1),
      twink: rand(0, Math.PI * 2),
    })),
    maxDist: 160,
  };

  function drawConstellations(t) {
    const nodes = constellations.nodes;
    const mx = mouseX, my = mouseY;

    nodes.forEach(n => {
      n.twink += 0.018;
      n.x += n.vx; n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    });

    // Lines between nearby nodes
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[j].x - nodes[i].x;
        const dy = nodes[j].y - nodes[i].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < constellations.maxDist) {
          const a = (1 - d / constellations.maxDist) * 0.2;
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = `rgba(160,200,255,${a})`;
          ctx.lineWidth = 0.6;
          ctx.stroke();
        }
      }
    }

    // Mouse attractor — lines from nearby nodes to cursor
    nodes.forEach(n => {
      const dx = mx - n.x, dy = my - n.y;
      const d = Math.sqrt(dx * dx + dy * dy);
      if (d < 200) {
        const a = (1 - d / 200) * 0.35;
        ctx.beginPath();
        ctx.moveTo(n.x, n.y);
        ctx.lineTo(mx, my);
        ctx.strokeStyle = `rgba(201,168,76,${a})`;
        ctx.lineWidth = 0.7;
        ctx.stroke();
      }
    });

    // Nodes
    nodes.forEach(n => {
      const pulse = 0.5 + 0.5 * Math.sin(n.twink);
      ctx.beginPath();
      ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(200,220,255,${n.a * pulse})`;
      ctx.fill();
    });

    // Cursor glow
    const cg = ctx.createRadialGradient(mx, my, 0, mx, my, 180);
    cg.addColorStop(0, 'rgba(201,168,76,0.08)');
    cg.addColorStop(1, 'transparent');
    ctx.fillStyle = cg;
    ctx.fillRect(0, 0, W, H);
  }

  // ─────────────────────────────────────────────
  //  SCENE 4 — NEBULA / DEEP SPACE (Reviews)
  // ─────────────────────────────────────────────
  const nebula = {
    dust: Array.from({ length: 6 }, (_, i) => ({
      x: rand(W * 0.1, W * 0.9),
      y: rand(H * 0.1, H * 0.9),
      r: rand(120, 300),
      hue: [260, 200, 300, 180, 280, 220][i],
      phase: rand(0, Math.PI * 2),
      speed: rand(0.003, 0.008),
    })),
    fireflies: Array.from({ length: 40 }, () => ({
      x: rand(0, W), y: rand(0, H),
      r: rand(0.5, 2.5),
      phase: rand(0, Math.PI * 2),
      speed: rand(0.015, 0.04),
      vx: rand(-0.1, 0.1), vy: rand(-0.1, 0.1),
      hue: rand(240, 300),
    })),
  };

  function drawNebula(t) {
    // Nebula clouds
    nebula.dust.forEach(d => {
      d.phase += d.speed;
      const a = 0.06 + 0.035 * Math.sin(d.phase);
      const grad = ctx.createRadialGradient(d.x, d.y, 0, d.x, d.y, d.r);
      grad.addColorStop(0, `hsla(${d.hue},80%,70%,${a * 2})`);
      grad.addColorStop(0.4, `hsla(${d.hue},70%,60%,${a})`);
      grad.addColorStop(1, 'transparent');
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = grad;
      ctx.fill();
    });

    // Floating sparkles
    nebula.fireflies.forEach(f => {
      f.phase += f.speed;
      f.x += f.vx + Math.sin(f.phase * 0.5) * 0.12;
      f.y += f.vy + Math.cos(f.phase * 0.4) * 0.1;
      if (f.x < 0) f.x = W; if (f.x > W) f.x = 0;
      if (f.y < 0) f.y = H; if (f.y > H) f.y = 0;
      const a = (0.4 + 0.6 * Math.sin(f.phase)) * 0.9;
      const fg = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, f.r * 3);
      fg.addColorStop(0, `hsla(${f.hue},90%,85%,${a})`);
      fg.addColorStop(1, 'transparent');
      ctx.beginPath();
      ctx.arc(f.x, f.y, f.r * 3, 0, Math.PI * 2);
      ctx.fillStyle = fg;
      ctx.fill();
    });

    // Deep vignette
    const vig = ctx.createRadialGradient(W / 2, H / 2, H * 0.2, W / 2, H / 2, H * 0.8);
    vig.addColorStop(0, 'transparent');
    vig.addColorStop(1, 'rgba(5,5,20,0.5)');
    ctx.fillStyle = vig;
    ctx.fillRect(0, 0, W, H);
  }

  // ─────────────────────────────────────────────
  //  ANIMATION LOOP
  // ─────────────────────────────────────────────
  let activeScene = 0;
  let t = 0;

  function tick() {
    requestAnimationFrame(tick);
    t += 0.016;

    mouseX = lerp(mouseX, targetMX, 0.05);
    mouseY = lerp(mouseY, targetMY, 0.05);

    ctx.clearRect(0, 0, W, H);

    if (activeScene === 0) drawAurora(t);
    else if (activeScene === 1) drawBokeh(t);
    else if (activeScene === 2) drawStreaks(t);
    else if (activeScene === 3) drawConstellations(t);
    else if (activeScene === 4) drawNebula(t);
  }
  tick();

  // ─────────────────────────────────────────────
  //  SCROLL SNAP + NAV
  // ─────────────────────────────────────────────
  const scrollWrap = document.getElementById('scroll-wrap');
  const navLinks = document.querySelectorAll('.nav-link');

  function setScene(idx) {
    activeScene = idx;
    navLinks.forEach((l, i) => l.classList.toggle('active', i === idx));
  }

  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const idx = parseInt(link.dataset.section);
      scrollWrap.scrollTo({ top: idx * window.innerHeight, behavior: 'smooth' });
    });
  });

  scrollWrap.addEventListener('scroll', () => {
    const idx = Math.round(scrollWrap.scrollTop / window.innerHeight);
    if (idx !== activeScene) setScene(idx);
  });

  // Chips → jump to hotels
  document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => scrollWrap.scrollTo({ top: window.innerHeight, behavior: 'smooth' }));
  });

  // Search
  document.getElementById('search-go').addEventListener('click', () => {
    const dest = document.getElementById('s-dest').value.trim();
    if (dest) scrollWrap.scrollTo({ top: window.innerHeight, behavior: 'smooth' });
  });

  // ─────────────────────────────────────────────
  //  SIDEBAR
  // ─────────────────────────────────────────────
  const sidebar = document.getElementById('sidebar');
  const sidebarMask = document.getElementById('sidebar-mask');
  const sidebarList = document.getElementById('sidebar-list');
  const sidebarEmpty = document.getElementById('sidebar-empty');
  const sidebarBottom = document.getElementById('sidebar-bottom');
  const sidebarTotal = document.getElementById('sidebar-total');
  const tripBadge = document.getElementById('trip-badge');
  let tripItems = [];

  const openSidebar = () => { sidebar.classList.add('open'); sidebarMask.classList.add('open'); };
  const closeSidebar = () => { sidebar.classList.remove('open'); sidebarMask.classList.remove('open'); };

  document.getElementById('trip-btn').addEventListener('click', openSidebar);
  document.getElementById('sidebar-x').addEventListener('click', closeSidebar);
  sidebarMask.addEventListener('click', closeSidebar);

  function parsePrice(str) { return parseInt((str || '').replace(/[^0-9]/g, '')) || 0; }

  function renderSidebar() {
    tripBadge.textContent = tripItems.length;
    const empty = tripItems.length === 0;
    sidebarEmpty.style.display = empty ? 'block' : 'none';
    sidebarBottom.style.display = empty ? 'none' : 'block';
    let total = 0;
    sidebarList.innerHTML = tripItems.map((item, i) => {
      total += parsePrice(item.price);
      return `<div class="sidebar-item">
        <span class="s-emoji">${item.emoji}</span>
        <div class="s-info">
          <div class="s-name">${item.name}</div>
          <div class="s-price">${item.price}</div>
        </div>
        <button class="s-del" data-idx="${i}">✕</button>
      </div>`;
    }).join('');
    sidebarTotal.textContent = '€' + total.toLocaleString();

    sidebarList.querySelectorAll('.s-del').forEach(btn => {
      btn.addEventListener('click', e => {
        const idx = parseInt(e.currentTarget.dataset.idx);
        const removed = tripItems.splice(idx, 1)[0];
        // re-enable matching btn
        document.querySelectorAll(`[data-item-name="${removed.name}"]`).forEach(b => {
          b.classList.remove('added');
          b.textContent = b.dataset.originalText || 'Add +';
        });
        renderSidebar();
      });
    });
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-item-name]');
    if (!btn) return;
    const { itemName, itemPrice, itemEmoji } = btn.dataset;
    if (btn.classList.contains('added')) {
      tripItems = tripItems.filter(x => x.name !== itemName);
      btn.classList.remove('added');
      btn.textContent = btn.dataset.originalText || 'Add +';
    } else {
      tripItems.push({ name: itemName, price: itemPrice, emoji: itemEmoji });
      btn.classList.add('added');
      btn.textContent = '✓ Added';
      openSidebar();
    }
    renderSidebar();
  });

  // ─────────────────────────────────────────────
  //  CONTENT INJECTION
  // ─────────────────────────────────────────────

  // HOTELS
  const hotels = [
    { name:'Amanjiwo Resort', loc:'Java, Indonesia', type:'RESORT', price:'€480', per:'/night', r:'4.9', img:'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=600&q=75', emoji:'🏨' },
    { name:'Soneva Fushi', loc:'Baa Atoll, Maldives', type:'VILLA', price:'€890', per:'/night', r:'5.0', img:'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=600&q=75', emoji:'🌴' },
    { name:'Burj Al Arab', loc:'Dubai, UAE', type:'LUXURY', price:'€1,200', per:'/night', r:'4.8', img:'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=600&q=75', emoji:'🌃' },
    { name:'Amangiri', loc:'Utah, USA', type:'DESERT', price:'€950', per:'/night', r:'4.9', img:'https://images.unsplash.com/photo-1596436889106-be35e843f974?w=600&q=75', emoji:'🏜️' },
  ];
  document.getElementById('hotels-row').innerHTML = hotels.map(h => `
    <div class="hotel-card">
      <div style="overflow:hidden;height:160px">
        <img class="hotel-photo" src="${h.img}" alt="${h.name}" loading="lazy" />
      </div>
      <div class="hotel-body">
        <div class="hotel-type">${h.type}</div>
        <div class="hotel-name">${h.name}</div>
        <div class="hotel-loc">📍 ${h.loc}</div>
        <div class="hotel-foot">
          <div class="hotel-price">${h.price}<small>${h.per}</small></div>
          <button class="add-btn"
            data-item-name="${h.name}"
            data-item-price="${h.price}"
            data-item-emoji="${h.emoji}"
            data-original-text="Add +"
          >Add +</button>
        </div>
      </div>
    </div>`).join('');

  // FLIGHTS
  const flights = [
    { from:'CDG', fromCity:'Paris', to:'DXB', toCity:'Dubai', dur:'6h 45m', cls:'Business', price:'€2,140', seats:'3 left', emoji:'✈️' },
    { from:'LHR', fromCity:'London', to:'MLE', toCity:'Malé', dur:'9h 20m', cls:'First Class', price:'€3,890', seats:'1 left', emoji:'✈️' },
    { from:'JFK', fromCity:'New York', to:'NRT', toCity:'Tokyo', dur:'13h 10m', cls:'Business', price:'€1,960', seats:'5 left', emoji:'✈️' },
    { from:'SIN', fromCity:'Singapore', to:'LAX', toCity:'Los Angeles', dur:'17h 30m', cls:'First Class', price:'€4,200', seats:'2 left', emoji:'✈️' },
  ];
  document.getElementById('flights-col').innerHTML = flights.map(f => `
    <div class="flight-card">
      <div class="flight-airline">✈</div>
      <div class="flight-route">
        <div class="flight-city"><strong>${f.from}</strong><small>${f.fromCity}</small></div>
        <div class="flight-mid">
          <div class="flight-dur">${f.dur}</div>
          <svg class="flight-line-svg" viewBox="0 0 200 16"><line x1="0" y1="8" x2="188" y2="8" stroke="rgba(201,168,76,0.5)" stroke-width="1"/><polygon points="188,4 200,8 188,12" fill="rgba(201,168,76,0.7)"/></svg>
          <div class="flight-cls">${f.cls}</div>
        </div>
        <div class="flight-city"><strong>${f.to}</strong><small>${f.toCity}</small></div>
      </div>
      <div class="flight-meta">
        <div class="flight-price">${f.price}</div>
        <div class="flight-seats">🔴 ${f.seats}</div>
        <button class="add-btn"
          data-item-name="${f.fromCity} → ${f.toCity}"
          data-item-price="${f.price}"
          data-item-emoji="${f.emoji}"
          data-original-text="Book"
        >Book</button>
      </div>
    </div>`).join('');

  // ACTIVITIES
  const acts = [
    { name:'Hot Air Balloon', sub:'Cappadocia, Türkiye', dur:'3h', price:'€180', img:'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=500&q=75', pin:'Türkiye', emoji:'🎈' },
    { name:'Great Barrier Reef', sub:'Cairns, Australia', dur:'5h', price:'€220', img:'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?w=500&q=75', pin:'Australia', emoji:'🤿' },
    { name:'Northern Lights', sub:'Tromsø, Norway', dur:'6h', price:'€340', img:'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=500&q=75', pin:'Norway', emoji:'🌌' },
    { name:'Machu Picchu Trek', sub:'Cusco, Peru', dur:'Full day', price:'€290', img:'https://images.unsplash.com/photo-1526392060635-9d6019884377?w=500&q=75', pin:'Peru', emoji:'🏔️' },
  ];
  document.getElementById('acts-row').innerHTML = acts.map(a => `
    <div class="act-card">
      <img class="act-photo" src="${a.img}" alt="${a.name}" loading="lazy" />
      <div class="act-overlay"></div>
      <div class="act-pin">📍 ${a.pin}</div>
      <div class="act-body">
        <div class="act-name">${a.name}</div>
        <div class="act-meta">
          <span class="act-dur">⏱ ${a.dur}</span>
          <span class="act-price">${a.price}</span>
        </div>
        <button class="act-add"
          data-item-name="${a.name}"
          data-item-price="${a.price}"
          data-item-emoji="${a.emoji}"
          data-original-text="Add to Trip"
        >Add to Trip</button>
      </div>
    </div>`).join('');

  // REVIEWS
  const reviews = [
    { text:'"Horizon AI completely transformed how I travel. The AI matched me with a resort I\'d never have found — it was paradise."', name:'Sophie Martin', loc:'Paris, France', img:'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&q=70' },
    { text:'"The search experience is unlike anything I\'ve used. I felt like I was already at my destination before even booking."', name:'James Chen', loc:'Singapore', img:'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&q=70' },
    { text:'"From flights to activities, flawlessly planned. My Northern Lights trip was a dream — all in one beautiful platform."', name:'Emma Dubois', loc:'Geneva, Switzerland', img:'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=120&q=70' },
  ];
  document.getElementById('reviews-grid').innerHTML = reviews.map(r => `
    <div class="review-card">
      <div class="review-stars">★★★★★</div>
      <div class="review-quote">${r.text}</div>
      <div class="review-author">
        <img class="review-av" src="${r.img}" alt="${r.name}" loading="lazy" />
        <div>
          <div class="review-name">${r.name}</div>
          <div class="review-loc">📍 ${r.loc}</div>
        </div>
      </div>
    </div>`).join('');

  renderSidebar();
  setScene(0);

  // ─────────────────────────────────────────────
  //  VIDEO BACKGROUND MANAGEMENT (Intersection Observer)
  //  Guarantees smooth playback only when visible
  // ─────────────────────────────────────────────
  const allVideos = document.querySelectorAll('.panel-vid');

  // Fade each video in once it can play
  allVideos.forEach(vid => {
    vid.playbackRate = 1.0; // Reset to hardware-optimized native speed 
    vid.addEventListener('canplay', () => vid.classList.add('loaded'), { once: true });
  });

  // Use Intersection Observer for guaranteed smooth transitions when scrolling
  const videoObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const panel = entry.target;
      const vid = panel.querySelector('.panel-vid');
      
      if (entry.isIntersecting) {
        panel.classList.add('is-active');
        if (vid) vid.play().catch(() => {});
      } else {
        panel.classList.remove('is-active');
        if (vid) vid.pause();
      }
    });
  }, { threshold: 0.4 }); // Trigger when 40% of the section is visible

  // Observe all panels
  document.querySelectorAll('.panel').forEach(panel => {
    videoObserver.observe(panel);
  });

  // Patch scroll listener for just the activeScene (canvas effects)
  scrollWrap.addEventListener('scroll', () => {
    const idx = Math.round(scrollWrap.scrollTop / window.innerHeight);
    if (idx !== activeScene) setScene(idx);
  });

  // Patch nav links
  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const idx = parseInt(link.dataset.section);
      setScene(idx);
      scrollWrap.scrollTo({ top: idx * window.innerHeight, behavior: 'smooth' });
    });
  });

})();
