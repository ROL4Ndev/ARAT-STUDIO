document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;

    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-label', `${visible ? 'Ocultar' : 'Mostrar'} ${button.dataset.passwordLabel}`);
        button.setAttribute('aria-pressed', String(visible));
    });
});

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.hasAttribute('data-estimate-endpoint')) {
        return;
    }

    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        return;
    }

    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    form.classList.add('form-loading');

    const button = event.submitter ?? form.querySelector('button[type="submit"], button:not([type])');
    if (button instanceof HTMLButtonElement) {
        button.dataset.originalLabel = button.textContent.trim();
        button.disabled = true;
        button.textContent = button.dataset.loadingLabel ?? 'Enviando…';
    }
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.classList.remove('form-loading');
        const button = form.querySelector('button:disabled[data-original-label]');
        if (button) {
            button.disabled = false;
            button.textContent = button.dataset.originalLabel;
            delete button.dataset.originalLabel;
        }
    });
});
