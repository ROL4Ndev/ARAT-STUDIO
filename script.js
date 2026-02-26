/* ─────────────────────────────────────────────────────
   ARAT STUDIO — script.js
   ───────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Año footer ── */
  const yr = document.getElementById('footer-year');
  if (yr) yr.textContent = new Date().getFullYear();

  /* ── Navbar scrolled ── */
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });

  /* ── Smooth scroll con offset correcto ── */
  const NAVBAR_H = () => navbar.offsetHeight + 4;

  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
      const hash = link.getAttribute('href');
      if (!hash || hash === '#') return;
      const target = document.querySelector(hash);
      if (!target) return;
      e.preventDefault();

      const navMenu = document.getElementById('navMenu');
      if (navMenu?.classList.contains('show')) {
        bootstrap.Collapse.getInstance(navMenu)?.hide();
      }

      let top;
      if (hash === '#inicio') {
        top = 0;
      } else if (hash === '#contacto') {
        top = target.offsetTop - NAVBAR_H() + 50;
      } else {
        top = target.offsetTop - NAVBAR_H();
      }

      window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    });
  });

  /* ── Active nav link ── */
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('#navbar .nav-link');

  const setActive = () => {
    const offset = NAVBAR_H() + 10;
    let current = '';
    sections.forEach(s => { if (window.scrollY + offset >= s.offsetTop) current = s.id; });
    navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === `#${current}`));
  };
  window.addEventListener('scroll', setActive, { passive: true });
  setActive();

  /* ── Reveal on scroll ── */
  const reveals = document.querySelectorAll('.js-reveal');
  if (reveals.length) {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-visible'); obs.unobserve(e.target); } });
    }, { threshold: 0.12 });
    reveals.forEach(el => obs.observe(el));
  }

  /* ════════════════════════════════════════════════════
     PROJECT OVERLAY + ZOOM/PAN PROFESIONAL
     ════════════════════════════════════════════════════ */

  const overlay   = document.getElementById('project-overlay');
  if (!overlay) return;

  const backdrop  = overlay.querySelector('.project-overlay__backdrop');
  const closeBtn  = overlay.querySelector('.project-overlay__close');
  const mainImg   = document.getElementById('overlay-main-img');
  const imgWrap   = document.getElementById('overlay-img-wrap');
  const titleEl   = document.getElementById('overlay-title');
  const descEl    = document.getElementById('overlay-desc');
  const galleryEl = document.getElementById('overlay-gallery');
  const zoomHint  = document.getElementById('overlay-zoom-hint');

  const MIN_SCALE = 1;
  const MAX_SCALE = 4;
  let scale = 1, tx = 0, ty = 0;
  let dragging = false, hasDragged = false;
  let dragStartX = 0, dragStartY = 0, dragStartTx = 0, dragStartTy = 0;
  let hintHidden = false;
  let lastTouchDist = 0, lastTouchMidX = 0, lastTouchMidY = 0;
  let touchStartTx = 0, touchStartTy = 0;
  let singleTouchStartX = 0, singleTouchStartY = 0;

  function getBounds() {
    if (!imgWrap || !mainImg) return { w: 0, h: 0, iw: 0, ih: 0 };
    const rect = imgWrap.getBoundingClientRect();
    const w = rect.width, h = rect.height;
    const natW = mainImg.naturalWidth || w;
    const natH = mainImg.naturalHeight || h;
    const ratio = Math.min(w / natW, h / natH);
    return { w, h, iw: natW * ratio, ih: natH * ratio };
  }

  function applyTransform(animate = false) {
    if (!mainImg || !imgWrap) return;
    mainImg.classList.toggle('no-transition', !animate);
    const b = getBounds();
    const maxTx = Math.max(0, (b.iw * scale - b.w) / 2);
    const maxTy = Math.max(0, (b.ih * scale - b.h) / 2);
    tx = Math.max(-maxTx, Math.min(maxTx, tx));
    ty = Math.max(-maxTy, Math.min(maxTy, ty));
    mainImg.style.transform = `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px)) scale(${scale})`;
  }

  function resetZoom(animate = true) {
    scale = 1; tx = 0; ty = 0;
    imgWrap?.classList.remove('state-zoomed', 'state-dragging');
    applyTransform(animate);
  }

  function zoomToPoint(cx, cy, newScale) {
    if (!imgWrap) return;
    const b = getBounds();
    const px = cx - b.w / 2 - tx;
    const py = cy - b.h / 2 - ty;
    const ds = newScale / scale;
    tx = tx - px * (ds - 1);
    ty = ty - py * (ds - 1);
    scale = newScale;
    imgWrap.classList.toggle('state-zoomed', scale > 1);
    applyTransform(true);
    if (!hintHidden && zoomHint) { hintHidden = true; zoomHint.classList.add('hidden'); }
  }

  function loadImage(src, alt) {
    if (!mainImg) return;
    mainImg.classList.add('no-transition');
    mainImg.src = src;
    mainImg.alt = alt;
    mainImg.style.top = '50%';
    mainImg.style.left = '50%';
    mainImg.style.width = '100%';
    mainImg.style.height = '100%';
    mainImg.style.transform = 'translate(-50%, -50%) scale(1)';
    mainImg.style.objectFit = 'contain';
    const onLoad = () => {
      requestAnimationFrame(() => {
        resetZoom(false);
        mainImg.classList.remove('no-transition');
      });
    };
    if (mainImg.complete && mainImg.naturalWidth) { onLoad(); }
    else { mainImg.onload = onLoad; }
  }

  function openOverlay(card) {
    try {
      const imgs  = JSON.parse(card.dataset.imgs || '[]');
      const title = card.dataset.title || '';
      const desc  = card.dataset.desc  || '';
      if (!imgs.length) return;

      titleEl.textContent = title;
      descEl.textContent  = desc;
      hintHidden = false;
      zoomHint?.classList.remove('hidden');

      loadImage(imgs[0], title);

      galleryEl.innerHTML = '';
      if (imgs.length > 1) {
        imgs.forEach((src, i) => {
          const div = document.createElement('div');
          div.className = 'overlay-thumb' + (i === 0 ? ' active-thumb' : '');
          const im = document.createElement('img');
          im.src = src; im.alt = `${title} ${i + 1}`; im.loading = 'lazy';
          div.appendChild(im);
          div.addEventListener('click', e => {
            e.stopPropagation();
            loadImage(src, `${title} ${i + 1}`);
            galleryEl.querySelectorAll('.overlay-thumb').forEach(t => t.classList.remove('active-thumb'));
            div.classList.add('active-thumb');
          });
          galleryEl.appendChild(div);
        });
      }

      overlay.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    } catch (err) {
      console.warn('Error al abrir proyecto:', err);
    }
  }

  function closeOverlay() {
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    setTimeout(() => {
      if (mainImg) mainImg.src = '';
      if (galleryEl) galleryEl.innerHTML = '';
      resetZoom(false);
    }, 380);
  }

  document.querySelectorAll('.project-card').forEach(card => {
    card.addEventListener('click', () => openOverlay(card));
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openOverlay(card); }
    });
  });

  if (imgWrap && mainImg) {

    imgWrap.addEventListener('click', e => {
      if (hasDragged) return;
      const rect = imgWrap.getBoundingClientRect();
      zoomToPoint(e.clientX - rect.left, e.clientY - rect.top, scale < 1.5 ? 2.5 : 1);
    });

    imgWrap.addEventListener('wheel', e => {
      e.preventDefault();
      const rect = imgWrap.getBoundingClientRect();
      const newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale * (e.deltaY < 0 ? 1.18 : 0.85)));
      if (newScale !== scale) zoomToPoint(e.clientX - rect.left, e.clientY - rect.top, newScale);
    }, { passive: false });

    imgWrap.addEventListener('mousedown', e => {
      if (scale <= 1) return;
      e.preventDefault();
      dragging = true; hasDragged = false;
      dragStartX = e.clientX; dragStartY = e.clientY;
      dragStartTx = tx; dragStartTy = ty;
      imgWrap.classList.add('state-dragging');
      mainImg.classList.add('no-transition');
    });

    window.addEventListener('mousemove', e => {
      if (!dragging) return;
      const dx = e.clientX - dragStartX, dy = e.clientY - dragStartY;
      if (Math.abs(dx) > 2 || Math.abs(dy) > 2) hasDragged = true;
      tx = dragStartTx + dx; ty = dragStartTy + dy;
      applyTransform(false);
    });

    window.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging = false;
      imgWrap.classList.remove('state-dragging');
      mainImg.classList.remove('no-transition');
      applyTransform(true);
      setTimeout(() => { hasDragged = false; }, 50);
    });

    imgWrap.addEventListener('touchstart', e => {
      if (e.touches.length === 2) {
        const [t0, t1] = [e.touches[0], e.touches[1]];
        lastTouchDist = Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
        lastTouchMidX = (t0.clientX + t1.clientX) / 2;
        lastTouchMidY = (t0.clientY + t1.clientY) / 2;
        touchStartTx = tx; touchStartTy = ty;
      } else if (e.touches.length === 1 && scale > 1) {
        singleTouchStartX = e.touches[0].clientX;
        singleTouchStartY = e.touches[0].clientY;
        touchStartTx = tx; touchStartTy = ty;
        hasDragged = false;
      }
    }, { passive: true });

    imgWrap.addEventListener('touchmove', e => {
      e.preventDefault();
      if (e.touches.length === 2) {
        const [t0, t1] = [e.touches[0], e.touches[1]];
        const dist = Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
        const midX = (t0.clientX + t1.clientX) / 2;
        const midY = (t0.clientY + t1.clientY) / 2;
        const rect = imgWrap.getBoundingClientRect();
        const newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale * (dist / lastTouchDist)));
        zoomToPoint(midX - rect.left, midY - rect.top, newScale);
        lastTouchDist = dist;
        tx += midX - lastTouchMidX; ty += midY - lastTouchMidY;
        lastTouchMidX = midX; lastTouchMidY = midY;
        applyTransform(false);
      } else if (e.touches.length === 1 && scale > 1) {
        const dx = e.touches[0].clientX - singleTouchStartX;
        const dy = e.touches[0].clientY - singleTouchStartY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) hasDragged = true;
        tx = touchStartTx + dx; ty = touchStartTy + dy;
        applyTransform(false);
      }
    }, { passive: false });

    imgWrap.addEventListener('touchend', e => {
      if (e.changedTouches.length === 1 && !hasDragged && e.touches.length === 0) {
        if (scale <= 1) {
          const rect = imgWrap.getBoundingClientRect();
          zoomToPoint(e.changedTouches[0].clientX - rect.left, e.changedTouches[0].clientY - rect.top, 2.5);
        } else {
          resetZoom(true);
        }
      }
      hasDragged = false;
      applyTransform(true);
    }, { passive: true });

    imgWrap.addEventListener('touchcancel', () => {
      hasDragged = false;
      applyTransform(true);
    });
  }

  closeBtn?.addEventListener('click', closeOverlay);
  backdrop?.addEventListener('click', closeOverlay);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeOverlay();
  });

});