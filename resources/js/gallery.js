const dialog = document.getElementById('project-lightbox');
const links = [...document.querySelectorAll('[data-gallery-image]')];

// Los enlaces originales siguen funcionando sin JavaScript o sin soporte de dialog.
if (dialog?.showModal && links.length) {
    const image = dialog.querySelector('[data-gallery-full]');
    const caption = dialog.querySelector('[data-gallery-caption]');
    const previous = dialog.querySelector('[data-gallery-previous]');
    const next = dialog.querySelector('[data-gallery-next]');
    let current = 0;
    let opener;

    function display(index) {
        current = (index + links.length) % links.length;
        image.src = links[current].href;
        image.alt = links[current].querySelector('img').alt;
        caption.textContent = links[current].dataset.caption;
        previous.disabled = next.disabled = links.length === 1;
    }

    links.forEach((link, index) => link.addEventListener('click', event => {
        event.preventDefault();
        opener = link;
        display(index);
        dialog.showModal();
        dialog.querySelector('[data-gallery-close]').focus();
    }));
    previous.addEventListener('click', () => display(current - 1));
    next.addEventListener('click', () => display(current + 1));
    dialog.querySelector('[data-gallery-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => opener?.focus());
    dialog.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowLeft') display(current - 1);
        if (event.key === 'ArrowRight') display(current + 1);
        if (event.key === 'Home') display(0);
        if (event.key === 'End') display(links.length - 1);
    });
}
