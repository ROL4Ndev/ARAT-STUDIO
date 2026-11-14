import { chromium } from 'playwright-core';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const directory = 'docs/design/captures/review';
const state = JSON.parse(await readFile('storage/app/design-preview/state.json', 'utf8'));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
const page = await context.newPage();
const checks = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
const open = path => page.goto('http://127.0.0.1:8765' + path, { waitUntil: 'networkidle' });
const shot = async name => {
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    assert.equal(overflow, false, name);
    await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
    checks.push({ name, passed: true, overflow });
};
try {
    await open('/cotizador');
    const endpoint = '**/api/calcular-presupuesto';
    await page.route(endpoint, route => route.abort('failed'));
    await page.locator('#area_m2').fill('80');
    await page.getByRole('button', { name: 'Calcular estimación' }).click();
    await page.getByText('No pudimos consultar la estimación. Revisa tu conexión y vuelve a intentarlo.').waitFor();
    await shot('35-error-de-conexion-simulado');
    await page.unroute(endpoint);
    await page.getByRole('button', { name: 'Calcular estimación' }).click();
    await page.waitForFunction(() => !document.querySelector('#estimate-preview').hasAttribute('aria-busy'));
    assert.equal(await page.getByText('No pudimos consultar la estimación. Revisa tu conexión y vuelve a intentarlo.').count(), 0);

    await open('/solicitar-proyecto');
    await page.locator('#area_m2').fill('80');
    await page.waitForFunction(() => Boolean(document.querySelector('#estimate_token').value));
    await page.evaluate(() => {
        const area = document.querySelector('#area_m2');
        area.value = '85'; area.dispatchEvent(new Event('input', { bubbles: true }));
        if (document.querySelector('#estimate_token').value) throw new Error('La firma anterior sigue activa tras cambiar el área.');
    });
    checks.push({ name: 'La firma referencial se invalida inmediatamente al editar los datos', passed: true });
    await page.waitForFunction(() => Boolean(document.querySelector('#estimate_token').value));
    await page.locator('#client_email').fill('errores@example.test');
    await page.locator('#client_phone').fill('999888777'); await page.locator('#city').fill('Lima');
    await page.locator('#description').fill('Proyecto ficticio para comprobar los errores del formulario.');
    await page.locator('#consent').check(); await page.locator('#estimate_confirmed').check();
    await page.locator('#project-request').evaluate(form => { form.noValidate = true; });
    await page.getByRole('button', { name: 'Confirmar estimación y registrar solicitud' }).click();
    await page.waitForLoadState('networkidle');
    await page.locator('[data-validation-summary]').waitFor();
    assert.equal(await page.locator('#client_name').getAttribute('aria-invalid'), 'true');
    await page.locator('[data-validation-summary] a[href="#client_name"]').click();
    assert.equal(await page.locator('#client_name').evaluate(el => el === document.activeElement), true);
    await shot('36-validacion-junto-al-campo');
    const denied = await open('/mi-solicitud'); assert.equal(denied.status(), 403); await shot('37-acceso-no-autorizado');
    await open('/ingresar');
    await page.locator('#email').fill(state.email); await page.locator('#password').fill(state.password);
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click(); await page.waitForURL('**/admin');
    await open('/admin/proyectos?q=ningun-resultado-ficticio'); await shot('38-catalogo-sin-resultados');
    assert.equal(await page.locator('#q').inputValue(), 'ningun-resultado-ficticio');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Menú del panel' }).click();
    assert.equal(await page.locator('#workspace-navigation').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#workspace-navigation').isVisible(), false);
    checks.push({ name: 'Menú interno con teclado y conservación del filtro', passed: true });
    for (const width of [320, 768, 1024]) {
        await page.setViewportSize({ width, height: 900 });
        await open('/gestion'); await shot(`39-gestion-ancho-${width}`);
    }
    assert.deepEqual(errors, []);
} finally {
    await writeFile(`${directory}/checks-interactions.json`, JSON.stringify({ checks, errors }, null, 2));
    console.log(JSON.stringify({ checked: checks.length, errors }));
    await browser.close();
}
