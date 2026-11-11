const requestForm = document.getElementById('project-request');
if (requestForm) {
    const file = requestForm.elements.attachment;
    const link = requestForm.elements.external_url;
    let needsLink = false;
    file.addEventListener('change', () => {
        needsLink = Boolean(file.files[0] && file.files[0].size > 50 * 1024 * 1024);
        document.getElementById('file-error').textContent = needsLink ? 'El archivo supera 50 MB. Se retiró del envío: ingresa un enlace externo.' : '';
        if (needsLink) file.value = '';
        link.required = needsLink;
    });
    requestForm.addEventListener('submit', (event) => {
        if (file.files[0]?.size > 50 * 1024 * 1024 || (needsLink && !link.value)) {
            event.preventDefault();
            return;
        }
        requestForm.querySelector('button[type="submit"]').disabled = true;
    });
}
