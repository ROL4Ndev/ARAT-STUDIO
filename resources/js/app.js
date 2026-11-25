import '../../public/js/navigation.js';
import '../../public/js/interface.js';

if (document.querySelector('[data-gallery-image]')) import('./gallery.js');
if (document.querySelector('[data-studio-film]')) import('./studio-videos.js');

const portfolioFilter = document.querySelector('[data-portfolio-filter]');
if (portfolioFilter) {
    const category = portfolioFilter.querySelector('[name="category"]');
    category.addEventListener('change', () => portfolioFilter.requestSubmit());
    portfolioFilter.querySelector('[data-filter-submit]').hidden = true;
    portfolioFilter.querySelector('[data-filter-help]').textContent = 'Al cambiar la categoría se actualizará la página y volverás a la primera página de resultados.';
}

const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
if ('IntersectionObserver' in window && !reducedMotion.matches) {
    const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.classList.add('reveal-in');
            observer.unobserve(entry.target);
        }
    }, { threshold: 0.08 });
    document.querySelectorAll('.section-block .container, .project-card, .project-gallery > a').forEach(section => observer.observe(section));
    reducedMotion.addEventListener('change', event => {
        if (event.matches) observer.disconnect();
    });
}

const toggle = document.querySelector('[data-workspace-toggle]');
const sidebar = document.getElementById('workspace-navigation');
if (toggle && sidebar) {
    const close = () => { sidebar.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => {
        const open = sidebar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) { close(); toggle.focus(); }
    });
    document.addEventListener('click', event => {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) close();
    });
    matchMedia('(min-width: 992px)').addEventListener('change', event => {
        close();
        if (!event.matches && sidebar.contains(document.activeElement)) toggle.focus();
    });
}
