import { chromium } from 'playwright-core';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:8765';
const directory = 'docs/design/captures/review';
await mkdir(directory, { recursive: true });
const state = JSON.parse(await readFile('storage/app/design-preview/state.json', 'utf8'));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [], errors = [];
const onlyStates = process.argv.includes('--states-only');
const context = await browser.newContext({ locale: 'es-PE', viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('dialog', dialog => dialog.accept());
const open = async (path, expected = 200) => {
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    assert.equal(response.status(), expected, path);
};
const shot = async name => {
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.evaluate(async () => {
        document.querySelectorAll('img').forEach(img => { img.loading = 'eager'; });
        await Promise.all([...document.images].map(img => img.decode().catch(() => {})));
    });
    const health = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images].filter(i => !i.naturalWidth).map(i => i.getAttribute('src')),
        frameworks: [...document.styleSheets].map(sheet => sheet.href).filter(Boolean),
    }));
    await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
    checks.push({ name, path: new URL(page.url()).pathname, ...health });
    assert.equal(health.overflow, false, `${name}: desbordamiento horizontal`);
    assert.deepEqual(health.brokenImages, [], `${name}: imágenes rotas`);
};
const submit = async locator => { await locator.click(); await page.waitForLoadState('networkidle'); };
try {
    if (!onlyStates) {
    for (const [name, path] of [
        ['01-servicios','/servicios'], ['02-portafolio','/proyectos'], ['03-proyecto','/proyectos/proyecto-sala-t'],
        ['04-cotizador','/cotizador'], ['05-solicitud','/solicitar-proyecto'], ['06-acceso-cliente','/acceder-expediente'],
        ['07-ingreso','/ingresar'], ['08-nosotros','/nosotros'], ['09-contacto','/contacto'],
    ]) {
        await open(path); await shot(name);
        await page.setViewportSize({ width: 390, height: 844 }); await shot(`${name}-movil`);
        await page.setViewportSize({ width: 1440, height: 1000 });
    }
    await open('/'); await page.keyboard.press('Tab');
    assert.equal(await page.locator('.skip-link').evaluate(el => el === document.activeElement), true);
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('main').evaluate(el => el === document.activeElement), true);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: /Menú/ }).click();
    assert.equal(await page.locator('#site-navigation').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#site-navigation').isVisible(), false);
    checks.push({ name: 'Teclado: salto al contenido y menú móvil con Escape', passed: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    // Recorrido completo sobre datos ficticios, usando los formularios reales.
    await open('/cotizador');
    const bim = await page.locator('#service_id option').filter({ hasText: 'Modelado BIM' }).getAttribute('value');
    await page.locator('#service_id').selectOption(bim);
    await page.locator('#area_m2').fill('80');
    await page.locator('#lod_selected').selectOption('LOD_300');
    await submit(page.getByRole('button', { name: 'Calcular estimación' }));
    await page.locator('#request-project-link').click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('#area_m2').inputValue(), '80');
    await page.locator('#client_name').fill('Cliente del recorrido visual');
    await page.locator('#client_email').fill('recorrido@example.test');
    await page.locator('#client_phone').fill('999888777');
    await page.locator('#city').fill('Lima');
    await page.locator('#description').fill('Diseño ficticio de vivienda con sala, cocina y dos dormitorios. Buscamos iluminación natural y ambientes integrados.');
    await page.locator('#consent').check();
    await page.waitForFunction(() => Boolean(document.querySelector('#estimate_token')?.value));
    await page.locator('#estimate_confirmed').check();
    await submit(page.getByRole('button', { name: 'Confirmar estimación y registrar solicitud' }));
    await page.waitForURL('**/solicitar-proyecto/confirmacion');
    const code = await page.locator('.workspace-hero strong').textContent();
    await shot('10-confirmacion');
    await open('/ingresar');
    await page.locator('#email').fill(state.email); await page.locator('#password').fill(state.password);
    await submit(page.getByRole('button', { name: 'Ingresar', exact: true }));
    await page.waitForURL('**/admin');
    await shot('11-panel');
    for (const [name, path] of [
        ['12-gestion','/gestion'], ['13-proyectos','/admin/proyectos'], ['14-categorias','/admin/categorias'],
        ['15-servicios','/admin/servicios'], ['16-tarifas','/admin/tarifas'], ['17-perfil','/perfil'],
        ['18-crear-proyecto','/admin/proyectos/create'], ['19-crear-categoria','/admin/categorias/create'],
        ['20-crear-servicio','/admin/servicios/create'], ['21-crear-tarifa','/admin/tarifas/create'],
        ['22-consultar-proyecto','/admin/proyectos/1'], ['23-editar-servicio','/admin/servicios/1/edit'],
        ['24-expediente-observado',`/gestion/solicitudes/${state.observed}`],
    ]) {
        await open(path); await shot(name);
        await page.setViewportSize({ width: 390, height: 844 }); await shot(`${name}-movil`);
        await page.setViewportSize({ width: 1440, height: 1000 });
    }
    await open(`/gestion/solicitudes/${code}`);
    await page.locator('#assign_reason').fill('Responsable asignado para la demostración aislada.');
    await submit(page.getByRole('button', { name: 'Confirmar asignación', exact: true }));
    await page.locator('#reason-start').fill('La información está completa para iniciar la evaluación.');
    await submit(page.getByRole('button', { name: 'Iniciar evaluación', exact: true }));
    await page.locator('#reason-approve').fill('Alcance viable según los datos entregados.');
    await page.locator('#assessment').fill('Evaluación técnica ficticia: el programa y el área permiten desarrollar el modelo y los planos solicitados.');
    await submit(page.getByRole('button', { name: 'Aprobar con evaluación técnica', exact: true }));
    await shot('25-aprobacion-tecnica');
    for (const [field, value] of Object.entries({
        scope: 'Diseño y modelado de una vivienda ficticia de 80 m².', deliverables: 'Modelo BIM y planos del proyecto.',
        exclusions: 'No incluye ejecución de obra ni trámites municipales.', execution_term: '20 días hábiles desde la coordinación inicial.',
        adjustment_reason: 'Importe definido tras evaluar el alcance ficticio.', reason: 'Primera propuesta de la demostración visual.',
    })) await page.locator(`#quote-${field}`).fill(value);
    await page.locator('#official_amount').fill('12000.50');
    await submit(page.getByRole('button', { name: 'Guardar borrador de cotización' }));
    await shot('26-borrador');
    await submit(page.getByRole('button', { name: 'Preparar emisión PDF', exact: true }));
    // El scheduler aislado procesa la emisión, igual que en la aplicación.
    for (let attempt = 0; attempt < 70; attempt++) {
        await page.reload({ waitUntil: 'networkidle' });
        if (await page.getByRole('link', { name: 'Descargar PDF inmutable de esta versión' }).count()) break;
        await page.waitForTimeout(1000);
    }
    await shot('27-emision');
    const pdfLink = page.getByRole('link', { name: 'Descargar PDF inmutable de esta versión' });
    assert.equal(await pdfLink.count(), 1);
    const pdf = await context.request.get(await pdfLink.getAttribute('href'));
    assert.equal(pdf.status(), 200);
    const bytes = await pdf.body(); assert.equal(bytes.subarray(0,4).toString(), '%PDF');
    await writeFile(`${directory}/cotizacion-demostracion.pdf`, bytes);
    await open(`/gestion/solicitudes/${code}`);
    await page.getByText('Demostración local del acceso del cliente', { exact: true }).click();
    await submit(page.getByRole('button', { name: 'Preparar acceso de cliente para demostración' }));
    const access = await page.locator('#demo-access-link').inputValue();
    await context.clearCookies();
    await page.goto(access, { waitUntil: 'networkidle' }); await shot('28-verificar-acceso');
    await submit(page.getByRole('button', { name: 'Verificar y abrir expediente' }));
    await shot('29-propuesta-cliente');
    await submit(page.getByRole('button', { name: 'Aceptar propuesta vigente' }));
    await shot('30-aceptacion-y-cierre');
    assert.equal(await page.getByRole('button', { name: 'Aceptar propuesta vigente' }).count(), 0);
    assert.equal(await page.getByText('Proceso finalizado', { exact: true }).count(), 1);
    checks.push({ name: 'Solicitud → responsable → evaluación → aprobación → borrador → emisión/PDF → acceso → aceptación → cierre', passed: true });
    }
    const enterClient = async code => {
        await context.clearCookies();
        await open('/ingresar');
        await page.locator('#email').fill(state.email); await page.locator('#password').fill(state.password);
        await submit(page.getByRole('button', { name: 'Ingresar', exact: true }));
        await open(`/gestion/solicitudes/${code}`);
        await page.getByText('Demostración local del acceso del cliente', { exact: true }).click();
        await submit(page.getByRole('button', { name: 'Preparar acceso de cliente para demostración' }));
        const url = await page.locator('#demo-access-link').inputValue();
        await context.clearCookies();
        await page.goto(url, { waitUntil: 'networkidle' });
        await submit(page.getByRole('button', { name: 'Verificar y abrir expediente' }));
    };
    await enterClient(state.observed);
    await shot('31-cliente-observado');
    assert.equal(await page.getByRole('link', { name: 'Completar información' }).count(), 1);
    await page.setViewportSize({ width: 390, height: 844 }); await shot('31-cliente-observado-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    if (state.expired) {
        await enterClient(state.expired); await shot('32-propuesta-vencida');
        assert.equal(await page.getByRole('button', { name: 'Aceptar propuesta vigente' }).count(), 0);
        assert.equal(await page.getByText('La propuesta ha vencido', { exact: true }).count(), 1);
    }
    await enterClient(state.request);
    await page.getByText(new RegExp('^Versión anterior 1')).click();
    await shot('33-version-anterior');
    assert.equal(await page.getByRole('button', { name: 'Aceptar propuesta vigente' }).count(), 1);
    const oldPdf = await context.request.get(await page.getByRole('link', { name: 'Descargar PDF de la versión 1' }).getAttribute('href'));
    assert.equal(oldPdf.status(), 200);
    checks.push({ name: 'Observaciones públicas, propuesta vencida y PDF de versión anterior', passed: true });
    assert.deepEqual(errors, []);
} finally {
    await writeFile(`${directory}/${onlyStates ? 'checks-states' : 'checks'}.json`, JSON.stringify({ checks, errors }, null, 2));
    console.log(JSON.stringify({ checked: checks.length, errors }, null, 2));
    await browser.close();
}
