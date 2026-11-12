document.querySelectorAll('[data-document-upload]').forEach((form) => {
    const file = form.elements.attachment;
    const link = form.elements.external_url;
    const error = form.querySelector('[data-file-error]');
    let needsLink = false;
    file.addEventListener('change', () => {
        needsLink = Boolean(file.files[0] && file.files[0].size > 50 * 1024 * 1024);
        error.textContent = needsLink ? 'El archivo supera 50 MiB y se retiró del envío. Usa un enlace externo para compartirlo.' : '';
        if (needsLink) file.value = '';
        link.required = needsLink;
    });
    form.addEventListener('submit', (event) => {
        if (file.files[0]?.size > 50 * 1024 * 1024 || (needsLink && !link.value)) {
            event.preventDefault();
            error.focus();
            return;
        }
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (button) button.disabled = true;
    });
});
