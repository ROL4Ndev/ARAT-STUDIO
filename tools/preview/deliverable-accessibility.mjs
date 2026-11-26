import { chromium } from 'playwright-core';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const state = JSON.parse(await readFile('storage/app/design-preview-final/document-state.json', 'utf8'));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [], errors = [];
try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('http://127.0.0.1:8765/ingresar');
    await page.locator('#email').fill(state.manager_email); await page.locator('#password').fill(state.password);
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click(); await page.waitForLoadState('networkidle');
    await page.getByRole('region', { name: 'Solicitudes recientes' }).getByRole('link', { name: state.request, exact: true }).click();
    await page.getByRole('link', { name: 'Preparar entregable', exact: true }).click();
    await page.locator('#delivery-title').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('#delivery-description').evaluate(node => node === document.activeElement), true);
    assert.equal(await page.locator('#delivery-description').evaluate(node => getComputedStyle(node).outlineStyle), 'solid');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('#delivery-file').evaluate(node => node === document.activeElement), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: 'docs/tercera-presentacion/capturas/17-formulario-teclado-390.png', fullPage: true });
    checks.push('Formulario a 390 px: secuencia de Tab, foco visible y sin desbordamiento horizontal');
    const noJs = await browser.newContext({ viewport: { width: 360, height: 844 }, javaScriptEnabled: false, storageState: await context.storageState() });
    const fallback = await noJs.newPage();
    assert.equal((await fallback.goto(page.url(), { waitUntil: 'networkidle' })).status(), 200);
    for (const id of ['#delivery-title', '#delivery-description', '#delivery-file', '#delivery-note']) assert.equal(await fallback.locator(id).isVisible(), true);
    assert.equal(await fallback.getByRole('button', { name: 'Guardar borrador', exact: true }).isVisible(), true);
    await fallback.screenshot({ path: 'docs/tercera-presentacion/capturas/18-formulario-sin-js-360.png', fullPage: true });
    checks.push('Formulario renderizado por Blade, campos y acción de guardado disponibles sin JavaScript a 360 px');
    assert.deepEqual(errors, []);
} finally {
    await writeFile('docs/tercera-presentacion/accessibility.json', JSON.stringify({ checks, errors }, null, 2));
    await browser.close();
    console.log(JSON.stringify({ checks: checks.length, errors }));
}
