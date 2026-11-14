import '../../public/js/navigation.js';
import '../../public/js/interface.js';

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
