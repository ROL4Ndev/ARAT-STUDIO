const form = document.getElementById('quotation-form');

if (form) {
    const area = form.elements.namedItem('area_m2');
    const lod = form.elements.namedItem('lod_selected');
    const result = document.getElementById('quotation-result');
    const amount = document.getElementById('quotation-amount');
    const message = document.getElementById('quotation-message');

    const currency = new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    });

    let timer;
    let controller;
    let version = 0;

    const resetRequest = () => {
        clearTimeout(timer);
        controller?.abort();
        version += 1;
        amount.textContent = '—';
        result.classList.remove('is-custom');
        result.setAttribute('aria-busy', 'false');
    };

    const calculate = async () => {
        resetRequest();

        if (!form.checkValidity()) {
            message.textContent = 'Ingresa un área válida y selecciona un nivel.';
            return;
        }

        const currentVersion = version;
        controller = new AbortController();

        result.setAttribute('aria-busy', 'true');
        message.textContent = 'Calculando estimación…';

        try {
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    area_m2: area.value,
                    lod_selected: lod.value,
                }),
                signal: controller.signal,
            });

            const data = await response.json();

            if (currentVersion !== version) return;

            if (!response.ok) {
                const firstError = Object.values(data.errors ?? {}).flat()[0];

                throw new Error(
                    response.status === 429
                        ? 'Demasiadas consultas. Espera un momento e inténtalo nuevamente.'
                        : firstError ?? 'No se pudo calcular. Inténtalo nuevamente.'
                );
            }

            result.classList.toggle('is-custom', data.out_of_scale);

            amount.textContent = data.out_of_scale
                ? 'Evaluación personalizada'
                : currency.format(data.estimated_cost);

            message.textContent = data.message;
        } catch (error) {
            if (error.name === 'AbortError' || currentVersion !== version) return;

            amount.textContent = '—';
            message.textContent = error instanceof TypeError
                ? 'No se pudo conectar con el servidor.'
                : error.message;
        } finally {
            if (currentVersion === version) {
                result.setAttribute('aria-busy', 'false');
            }
        }
    };

    form.addEventListener('input', () => {
        resetRequest();
        message.textContent = 'Actualizando estimación…';
        timer = setTimeout(calculate, 600);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        calculate();
    });

    calculate();
}
