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

const errorSummary = document.querySelector('[data-validation-summary]');
if (errorSummary) {
    errorSummary.querySelectorAll('[data-field-error]').forEach((error, index) => {
        const candidates = [...document.querySelectorAll(`[name="${CSS.escape(error.dataset.fieldError)}"]`)];
        const field = candidates.find(input => input.form?.elements.action?.value === errorSummary.dataset.errorAction) ?? candidates[0];
        if (!field || field.type === 'hidden') return;
        field.id ||= `invalid-field-${index}`;
        field.setAttribute('aria-invalid', 'true');
        let feedback = field.nextElementSibling?.matches('.form-feedback, .invalid-feedback') ? field.nextElementSibling : null;
        if (!feedback) {
            feedback = document.createElement('p');
            feedback.className = 'form-feedback';
            feedback.textContent = error.textContent;
            field.after(feedback);
        }
        feedback.id ||= `field-error-${index}`;
        const descriptions = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        descriptions.add(feedback.id);
        field.setAttribute('aria-describedby', [...descriptions].join(' '));
        const link = document.createElement('a');
        link.href = `#${field.id}`;
        link.textContent = error.textContent;
        link.addEventListener('click', () => {
            for (let parent = field.parentElement; parent; parent = parent.parentElement) {
                if (parent instanceof HTMLDetailsElement) parent.open = true;
            }
            field.focus();
        });
        error.replaceChildren(link);
    });
    errorSummary.focus();
}

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
