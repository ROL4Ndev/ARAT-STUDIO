document.querySelectorAll('[data-document-upload]').forEach((form) => {
    const file = form.elements.attachment;
    const link = form.elements.external_url;
    const error = form.querySelector('[data-file-error]');
    const selection = form.querySelector('[data-file-selection]');
    const removeButton = form.querySelector('[data-remove-file]');
    let needsLink = false;
    file.addEventListener('change', () => {
        const selected = file.files[0];
        needsLink = Boolean(selected && selected.size > 50 * 1024 * 1024);
        error.textContent = needsLink ? 'El archivo supera 50 MiB y se retiró del envío. Usa un enlace externo para compartirlo.' : '';
        if (needsLink) file.value = '';
        link.required = needsLink;
        if (selection) {
            selection.textContent = needsLink
                ? ''
                : selected
                    ? `${selected.name} · ${(selected.size / (1024 * 1024)).toFixed(2)} MiB`
                    : '';
        }
        removeButton?.classList.toggle('d-none', !selected || needsLink);
    });
    removeButton?.addEventListener('click', () => {
        file.value = '';
        needsLink = false;
        link.required = false;
        error.textContent = '';
        if (selection) selection.textContent = '';
        removeButton.classList.add('d-none');
        file.focus();
    });
    form.addEventListener('submit', (event) => {
        if (file.files[0]?.size > 50 * 1024 * 1024 || (needsLink && !link.value)) {
            event.preventDefault();
            error.focus();
        }
    });
});
