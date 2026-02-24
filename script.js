document.addEventListener('DOMContentLoaded', function () {

  var yearEl = document.getElementById('footer-year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  var navbar = document.getElementById('navbar');

  function handleNavbarScroll() {
    navbar.classList.toggle('scrolled', window.scrollY > 60);
  }
  window.addEventListener('scroll', handleNavbarScroll, { passive: true });
  handleNavbarScroll();

  var sections = document.querySelectorAll('section[id]');
  var navLinks = document.querySelectorAll('.navbar-nav .nav-link');

  function setActiveLink() {
    var scrollY = window.scrollY + 90;
    var currentId = '';
    sections.forEach(function (sec) {
      if (scrollY >= sec.offsetTop) currentId = sec.id;
    });
    navLinks.forEach(function (link) {
      link.classList.toggle('active', link.getAttribute('href') === '#' + currentId);
    });
  }
  window.addEventListener('scroll', setActiveLink, { passive: true });
  setActiveLink();

  var navCollapse = document.getElementById('navMenu');
  navLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      var bsCol = bootstrap.Collapse.getInstance(navCollapse);
      if (bsCol) bsCol.hide();
    });
  });

  var NAVBAR_H = 70;

  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var id = this.getAttribute('href');
      if (!id || id === '#') return;
      var target = document.querySelector(id);
      if (!target) return;
      e.preventDefault();
      var offset = (id === '#contacto') ? 0 : NAVBAR_H;
      var targetTop = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top: targetTop, behavior: 'smooth' });
    });
  });

  var REVEAL_SEL = '.service-card, .project-card, .director-card';
  document.querySelectorAll(REVEAL_SEL).forEach(function (el) {
    el.classList.add('js-reveal');
  });

  var revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.js-reveal').forEach(function (el) {
    revealObserver.observe(el);
  });

  /* ── OVERLAY ── */
  var overlay      = document.getElementById('project-overlay');
  var overlayPanel = overlay.querySelector('.project-overlay__panel');
  var overlayClose = overlay.querySelector('.project-overlay__close');
  var overlayBack  = overlay.querySelector('.project-overlay__backdrop');
  var mainImg      = document.getElementById('overlay-main-img');
  var overlayTitle = document.getElementById('overlay-title');
  var overlayDesc  = document.getElementById('overlay-desc');
  var gallery      = document.getElementById('overlay-gallery');
  var heroBox      = overlay.querySelector('.project-overlay__hero');

  function lockBody()   { document.body.style.overflow = 'hidden'; }
  function unlockBody() { document.body.style.overflow = ''; }

  /* ── ZOOM + PAN ── */
  var zoomScale   = 2.5;
  var isZoomed    = false;
  var isPanning   = false;
  var panX        = 0;
  var panY        = 0;
  var startX      = 0;
  var startY      = 0;
  var originX     = 50;
  var originY     = 50;

  function applyTransform() {
    if (isZoomed) {
      mainImg.style.transformOrigin = originX + '% ' + originY + '%';
      mainImg.style.transform = 'scale(' + zoomScale + ') translate(' + panX + 'px, ' + panY + 'px)';
    } else {
      mainImg.style.transform = 'scale(1) translate(0,0)';
      mainImg.style.transformOrigin = 'center center';
      panX = 0;
      panY = 0;
    }
  }

  function resetZoom() {
    isZoomed = false;
    isPanning = false;
    panX = 0;
    panY = 0;
    originX = 50;
    originY = 50;
    heroBox.classList.remove('zoomed');
    applyTransform();
  }

  heroBox.addEventListener('click', function (e) {
    if (isPanning) return;
    if (!isZoomed) {
      var rect = heroBox.getBoundingClientRect();
      originX = ((e.clientX - rect.left) / rect.width)  * 100;
      originY = ((e.clientY - rect.top)  / rect.height) * 100;
      isZoomed = true;
      heroBox.classList.add('zoomed');
    } else {
      isZoomed = false;
      heroBox.classList.remove('zoomed');
    }
    applyTransform();
  });

  /* Pan con mouse */
  heroBox.addEventListener('mousedown', function (e) {
    if (!isZoomed) return;
    e.preventDefault();
    isPanning = false;
    startX = e.clientX - panX;
    startY = e.clientY - panY;
    heroBox.style.cursor = 'grabbing';
  });

  window.addEventListener('mousemove', function (e) {
    if (!isZoomed || startX === null) return;
    var dx = e.clientX - startX;
    var dy = e.clientY - startY;
    if (Math.abs(dx - panX) > 3 || Math.abs(dy - panY) > 3) isPanning = true;
    panX = dx;
    panY = dy;
    applyTransform();
  });

  window.addEventListener('mouseup', function () {
    if (isZoomed) heroBox.style.cursor = 'zoom-out';
    setTimeout(function () { isPanning = false; }, 50);
  });

  /* Pan con touch */
  var touchStartX = 0;
  var touchStartY = 0;
  var touchPanX   = 0;
  var touchPanY   = 0;

  heroBox.addEventListener('touchstart', function (e) {
    if (!isZoomed || e.touches.length !== 1) return;
    touchStartX = e.touches[0].clientX - panX;
    touchStartY = e.touches[0].clientY - panY;
  }, { passive: true });

  heroBox.addEventListener('touchmove', function (e) {
    if (!isZoomed || e.touches.length !== 1) return;
    e.preventDefault();
    panX = e.touches[0].clientX - touchStartX;
    panY = e.touches[0].clientY - touchStartY;
    applyTransform();
  }, { passive: false });

  /* Wheel zoom */
  heroBox.addEventListener('wheel', function (e) {
    e.preventDefault();
    var rect = heroBox.getBoundingClientRect();
    originX = ((e.clientX - rect.left) / rect.width)  * 100;
    originY = ((e.clientY - rect.top)  / rect.height) * 100;

    if (e.deltaY < 0) {
      zoomScale = Math.min(zoomScale + 0.3, 5);
      isZoomed = true;
      heroBox.classList.add('zoomed');
    } else {
      zoomScale = Math.max(zoomScale - 0.3, 1);
      if (zoomScale <= 1) {
        zoomScale = 2.5;
        resetZoom();
        return;
      }
    }
    applyTransform();
  }, { passive: false });

  /* ── ABRIR OVERLAY ── */
  function openOverlay(card) {
    var title = card.getAttribute('data-title') || '';
    var desc  = card.getAttribute('data-desc')  || '';
    var imgs  = [];

    try { imgs = JSON.parse(card.getAttribute('data-imgs') || '[]'); } catch (e) {}

    if (!imgs.length) {
      var cardImg = card.querySelector('.project-card__media img');
      if (cardImg && cardImg.getAttribute('src')) imgs = [cardImg.getAttribute('src')];
    }

    overlayTitle.textContent = title;
    overlayDesc.textContent  = desc;

    resetZoom();
    mainImg.src = imgs[0] || '';
    mainImg.alt = title;

    gallery.innerHTML = '';
    imgs.forEach(function (src, i) {
      var thumb = document.createElement('div');
      thumb.className = 'overlay-thumb' + (i === 0 ? ' active-thumb' : '');

      var img     = document.createElement('img');
      img.src     = src;
      img.alt     = title + ' — imagen ' + (i + 1);
      img.loading = 'lazy';

      thumb.appendChild(img);
      gallery.appendChild(thumb);

      thumb.addEventListener('click', function () {
        resetZoom();
        mainImg.src = src;
        gallery.querySelectorAll('.overlay-thumb').forEach(function (t) {
          t.classList.remove('active-thumb');
        });
        thumb.classList.add('active-thumb');
      });
    });

    overlay.classList.add('is-open');
    overlayPanel.scrollTop = 0;
    lockBody();
    overlayClose.focus();
  }

  /* ── CERRAR OVERLAY ── */
  function closeOverlay() {
    overlay.classList.remove('is-open');
    unlockBody();
    resetZoom();
    mainImg.src       = '';
    gallery.innerHTML = '';
  }

  document.querySelectorAll('.project-card').forEach(function (card) {
    card.addEventListener('click', function () { openOverlay(card); });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openOverlay(card);
      }
    });
  });

  overlayClose.addEventListener('click', closeOverlay);
  overlayBack.addEventListener('click', closeOverlay);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeOverlay();
  });

});