const navigation = document.getElementById('site-navigation');
const menuToggle = document.querySelector('.site-menu-toggle');

if (navigation && menuToggle) {
    const mobile = window.matchMedia('(max-width: 1199.98px)');
    const submenus = [...navigation.querySelectorAll('.site-submenu')];
    const setMenu = open => {
        navigation.hidden = !open;
        menuToggle.setAttribute('aria-expanded', String(open));
    };
    const closeSubmenus = except => submenus.forEach(menu => {
        if (menu !== except) menu.open = false;
    });
    const resize = () => {
        if (mobile.matches && navigation.contains(document.activeElement)) menuToggle.focus();
        const focusFirstLink = !mobile.matches && document.activeElement === menuToggle;
        setMenu(!mobile.matches);
        if (focusFirstLink) navigation.querySelector('a').focus();
        closeSubmenus();
    };

    menuToggle.hidden = false;
    resize();
    mobile.addEventListener('change', resize);
    menuToggle.addEventListener('click', () => {
        setMenu(navigation.hidden);
        if (navigation.hidden) closeSubmenus();
    });
    submenus.forEach(menu => menu.addEventListener('toggle', () => {
        if (menu.open) closeSubmenus(menu);
    }));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const submenu = event.target.closest('.site-submenu[open]');
        if (submenu) {
            submenu.open = false;
            submenu.querySelector('summary').focus();
        } else if (mobile.matches && !navigation.hidden) {
            setMenu(false);
            closeSubmenus();
            menuToggle.focus();
        } else {
            closeSubmenus();
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('#navbar')) {
            closeSubmenus();
            if (mobile.matches) setMenu(false);
        }
    });
    navigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        closeSubmenus();
        if (mobile.matches) {
            setMenu(false);
            menuToggle.focus({ preventScroll: true });
        }
    }));
}
