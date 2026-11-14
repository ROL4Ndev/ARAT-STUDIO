document.querySelectorAll('[data-estimate-endpoint]').forEach(form => {
    const { service_id: service, area_m2: area, lod_selected: lod, estimate_token: token, estimate_confirmed: confirmation } = form.elements;
    const preview = form.querySelector('#estimate-preview');
    const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
    let timer, controller, sequence = 0, pending = false;

    const invalidate = () => {
        controller?.abort();
        sequence++;
        pending = false;
        if (token) token.value = '';
        if (confirmation) confirmation.checked = false;
        preview.removeAttribute('aria-busy');
    };

    const calculate = async () => {
        clearTimeout(timer);
        invalidate();
        const run = sequence;
        const manual = service.selectedOptions[0]?.dataset.mode === 'manual';
        area.disabled = lod.disabled = manual;
        area.required = lod.required = !manual;
        if (!service.value) {
            preview.textContent = 'No hay servicios disponibles para estimar en este momento.';
            return;
        }
        if (!manual && (!area.checkValidity() || !lod.value)) {
            preview.textContent = 'Completa un área válida y un nivel LOD.';
            return;
        }
        controller = new AbortController();
        pending = true;
        preview.textContent = 'Consultando estimación…';
        preview.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.dataset.estimateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ service_id: service.value, area_m2: manual ? null : area.value, lod_selected: manual ? null : lod.value }),
                signal: controller.signal,
            });
            const data = await response.json();
            if (run !== sequence) return;
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? 'No se pudo consultar. Intenta nuevamente.');
            const note = data.is_demo
                ? ' Las tarifas disponibles son demostrativas; no constituyen una cotización oficial.'
                : ' Esta estimación no constituye una cotización oficial.';
            preview.textContent = data.estimated_cost === null
                ? data.message + note
                : `Estimación referencial: ${currency.format(data.estimated_cost_decimal ?? data.estimated_cost)}.${note}`;
            if (token) token.value = data.estimate_token;
        } catch (error) {
            if (error.name !== 'AbortError' && run === sequence) {
                preview.textContent = error instanceof TypeError || error instanceof SyntaxError
                    ? 'No pudimos consultar la estimación. Revisa tu conexión y vuelve a intentarlo.'
                    : error.message;
            }
        } finally {
            if (run === sequence) {
                pending = false;
                preview.removeAttribute('aria-busy');
            }
        }
    };

    [service, area, lod].forEach(field => field.addEventListener('input', () => {
        invalidate();
        clearTimeout(timer);
        timer = setTimeout(calculate, 250);
    }));

    form.addEventListener('submit', event => {
        if (form.id === 'quotation-form' || pending || (token && !token.value)) {
            event.preventDefault();
            calculate();
            return;
        }
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.classList.add('form-loading');
        const button = event.submitter ?? form.querySelector('button[type="submit"]');
        if (button) {
            button.dataset.originalLabel = button.textContent.trim();
            button.disabled = true;
            button.textContent = button.dataset.loadingLabel ?? 'Enviando solicitud…';
        }
    });

    const link = form.querySelector('#request-project-link');
    const requestUrl = link?.href;
    link?.addEventListener('click', () => {
        const url = new URL(requestUrl);
        ['service_id', 'area_m2', 'lod_selected', 'project_type'].forEach(name => {
            const field = form.elements[name];
            if (!field.disabled) url.searchParams.set(name, field.value);
        });
        link.href = url.toString();
    });
    calculate();
});
