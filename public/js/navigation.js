const publicMenu = document.getElementById('mobile-navigation');
if (publicMenu) {
    const summary = publicMenu.querySelector('summary');
    publicMenu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        publicMenu.open = false;
        summary.focus({ preventScroll: true });
    }));
    publicMenu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            publicMenu.open = false;
            summary.focus();
        }
    });
}
