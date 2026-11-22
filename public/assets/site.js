/* Evolve Hire v1.3.0 — progressive enhancement layer.
   Everything here is additive: with JS off, the site still renders fully
   (reveal classes are only ever added by this file, never assumed). */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- 1. scroll reveal ----
     Cards, job rows, chips and facts get .rv (fade-up) and .rv-in when
     they enter the viewport. Staggered by DOM order within a section.
     Hidden elements (display:none pages in the single-file preview) are
     skipped — call window.evolveRebind() after showing a page. */
  function reveal(root) {
    if (reduce || !('IntersectionObserver' in window)) return;
    var sel = '.section .card, .section .job, .section .row.card-lift, .section .grid-3 > article, ' +
      '.section .chips .chip, .section .howto li, .cred div, .sb';
    var scope = root || document;
    var targets = scope.querySelectorAll ? scope.querySelectorAll(sel) : [];
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add('rv-in');
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    var stagger = 0;
    var lastParent = null;
    targets.forEach(function (el) {
      if (el.classList.contains('rv')) return; // already bound
      // never hide anything already inside the viewport at load — it would flash
      var r = el.getBoundingClientRect();
      if (r.top < window.innerHeight && r.bottom > 0) return;
      if (el.parentElement !== lastParent) { lastParent = el.parentElement; stagger = 0; }
      if (stagger < 4) stagger++;
      el.classList.add('rv', 'rv-d' + (stagger === 1 ? 1 : stagger === 2 ? 2 : stagger === 3 ? 3 : 4));
      io.observe(el);
    });
  }
  window.evolveRebind = function (root) { reveal(root); };

  /* ---- 2. stat counters ----
     .big elements that hold a plain number count up on first sight. */
  function counters() {
    if (reduce) return;
    document.querySelectorAll('.big').forEach(function (el) {
      var raw = (el.textContent || '').trim();
      if (!/^\d{1,6}$/.test(raw)) return;
      var end = parseInt(raw, 10);
      if (end < 3) return;
      var t0 = null, dur = 900;
      el.textContent = '0';
      function tick(ts) {
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = String(Math.round(end * eased));
        if (p < 1) requestAnimationFrame(tick);
      }
      if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (en) {
          if (en[0].isIntersecting) { cio.disconnect(); requestAnimationFrame(tick); }
        }, { threshold: 0.4 });
        cio.observe(el);
      } else { requestAnimationFrame(tick); }
    });
  }

  /* ---- 3. button ripple ----
     Buttons flash a soft ring from the press point. */
  function ripple() {
    document.addEventListener('pointerdown', function (e) {
      var b = e.target.closest && e.target.closest('.btn');
      if (!b || reduce) return;
      var r = b.getBoundingClientRect();
      b.style.setProperty('--bx', ((e.clientX - r.left) / r.width * 100) + '%');
      b.style.setProperty('--by', ((e.clientY - r.top) / r.height * 100) + '%');
      b.classList.remove('btn-ripple');
      void b.offsetWidth; /* restart animation */
      b.classList.add('btn-ripple');
    });
  }

  /* ---- 4. mobile nav closes after choosing a link ---- */
  function navClose() {
    var nav = document.getElementById('nav');
    var toggle = document.querySelector('.navtoggle');
    if (!nav || !toggle) return;
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A' && nav.hasAttribute('data-open')) {
        nav.removeAttribute('data-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---- 5. header gains a stronger shadow once scrolled ---- */
  function headerShadow() {
    var top = document.querySelector('.top');
    if (!top) return;
    var onScroll = function () {
      top.classList.toggle('scrolled', window.scrollY > 12);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }


  /* ---- 6. mobile menu (inline onclick was blocked by CSP, so it lives here) ---- */
  function menu() {
    var nav = document.getElementById('nav');
    var toggle = document.querySelector('.navtoggle');
    if (!nav || !toggle) return;
    function set(open) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) nav.setAttribute('data-open', 'true'); else nav.removeAttribute('data-open');
    }
    toggle.addEventListener('click', function () { set(toggle.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) set(false);
    });
  }

  /* ---- 7. scroll progress bar ---- */
  function progress() {
    var bar = document.createElement('div');
    bar.className = 'progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);
    var tick = false;
    function update() {
      var max = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(window.scrollY / max, 1) : 0) + ')';
      tick = false;
    }
    window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }

  /* ---- 8. cursor glow on cards (pointer devices only) ---- */
  function glow() {
    if (reduce || !window.matchMedia('(hover: hover)').matches) return;
    var raf = 0, ev = null;
    document.addEventListener('pointermove', function (e) {
      ev = e;
      if (raf) return;
      raf = requestAnimationFrame(function () {
        raf = 0;
        var c = ev.target.closest && ev.target.closest('.card, .row.card-lift');
        if (!c) return;
        var r = c.getBoundingClientRect();
        c.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
        c.style.setProperty('--my', (ev.clientY - r.top) + 'px');
      });
    }, { passive: true });
  }

  /* ---- 9. hero network: drifting nodes that link up and lean toward the pointer ---- */
  function network() {
    var cv = document.querySelector('.hero-net');
    if (!cv || !cv.getContext) return;
    var ctx = cv.getContext('2d');
    var w = 0, h = 0, dpr = 1, nodes = [], mouse = { x: -999, y: -999 }, running = false, raf = 0;
    function size() {
      var r = cv.parentElement.getBoundingClientRect();
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = r.width; h = r.height;
      cv.width = w * dpr; cv.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.max(18, Math.min(64, Math.round(w * h / 15000)));
      nodes = [];
      for (var i = 0; i < n; i++) nodes.push({ x: Math.random() * w, y: Math.random() * h, vx: (Math.random() - .5) * .28, vy: (Math.random() - .5) * .28, r: 1.2 + Math.random() * 1.8 });
    }
    function draw() {
      ctx.clearRect(0, 0, w, h);
      var link = w < 640 ? 90 : 128, i, j, a, b, dx, dy, d;
      for (i = 0; i < nodes.length; i++) {
        a = nodes[i];
        if (!reduce) {
          a.x += a.vx; a.y += a.vy;
          if (a.x < -10) a.x = w + 10; else if (a.x > w + 10) a.x = -10;
          if (a.y < -10) a.y = h + 10; else if (a.y > h + 10) a.y = -10;
        }
        for (j = i + 1; j < nodes.length; j++) {
          b = nodes[j]; dx = a.x - b.x; dy = a.y - b.y; d = dx * dx + dy * dy;
          if (d < link * link) {
            ctx.strokeStyle = 'rgba(159,192,238,' + (0.28 * (1 - Math.sqrt(d) / link)).toFixed(3) + ')';
            ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
          }
        }
        dx = a.x - mouse.x; dy = a.y - mouse.y; d = Math.sqrt(dx * dx + dy * dy);
        if (d < 150) {
          ctx.strokeStyle = 'rgba(255,255,255,' + (0.55 * (1 - d / 150)).toFixed(3) + ')';
          ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke();
        }
        ctx.fillStyle = 'rgba(200,220,250,.85)';
        ctx.beginPath(); ctx.arc(a.x, a.y, a.r, 0, 6.2832); ctx.fill();
      }
    }
    function loop() { draw(); raf = running ? requestAnimationFrame(loop) : 0; }
    function start() { if (!running && !reduce) { running = true; loop(); } }
    function stop() { running = false; }
    size(); draw();
    if (reduce) return;
    var hero = cv.parentElement;
    hero.addEventListener('pointermove', function (e) { var r = cv.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; }, { passive: true });
    hero.addEventListener('pointerleave', function () { mouse.x = mouse.y = -999; });
    var t = 0;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(function () { size(); draw(); }, 150); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { en[0].isIntersecting ? start() : stop(); }).observe(hero);
    } else { start(); }
    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
  }

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  ready(function () { reveal(); counters(); ripple(); navClose(); headerShadow(); menu(); progress(); glow(); network(); });
})();
