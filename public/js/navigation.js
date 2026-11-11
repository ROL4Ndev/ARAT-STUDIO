const publicMenu = document.getElementById('mobile-navigation');
if (publicMenu) {
    publicMenu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { publicMenu.open = false; }));
    publicMenu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            publicMenu.open = false;
            publicMenu.querySelector('summary').focus();
        }
    });
}
