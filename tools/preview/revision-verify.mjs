import { chromium } from 'playwright-core';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:8765';
const directory = 'docs/revision-2/captures/review';
const state = JSON.parse(await readFile('storage/app/design-preview-v2/state.json', 'utf8'));
await mkdir(directory, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [];
const errors = [];
const context = await browser.newContext({ locale: 'es-PE', viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('dialog', dialog => dialog.accept());
const open = async path => {
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200, path);
};
const shot = async name => {
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth);
    await page.evaluate(async () => {
        document.querySelectorAll('img[src]').forEach(image => image.loading = 'eager');
        await Promise.all([...document.images].filter(image => image.hasAttribute('src')).map(image => image.decode().catch(() => {})));
    });
    const health = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images].filter(image => image.hasAttribute('src') && !image.naturalWidth).length,
    }));
    assert.equal(health.overflow, false, `${name}: desbordamiento`);
    assert.equal(health.brokenImages, 0, `${name}: imágenes`);
    await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
    checks.push({ name, ...health });
};
const login = async (email, password) => {
    await open('/ingresar');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await page.waitForLoadState('networkidle');
};
const submit = async locator => {
    await locator.click();
    await page.waitForLoadState('networkidle');
};

try {
    await open('/');
    await page.evaluate(() => document.fonts.ready);
    const cdp = await context.newCDPSession(page);
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    const document = await cdp.send('DOM.getDocument');
    const title = await cdp.send('DOM.querySelector', { nodeId: document.root.nodeId, selector: '.home-hero h1' });
    const rendered = await cdp.send('CSS.getPlatformFontsForNode', { nodeId: title.nodeId });
    assert.ok(rendered.fonts.some(font => font.familyName === 'Kodchasan' && font.isCustomFont && font.glyphCount > 0));
    const paragraph = await cdp.send('DOM.querySelector', { nodeId: document.root.nodeId, selector: '.home-hero__copy > p:not(.hero__eyebrow)' });
    const bodyFonts = await cdp.send('CSS.getPlatformFontsForNode', { nodeId: paragraph.nodeId });
    assert.ok(bodyFonts.fonts.every(font => font.familyName !== 'Kodchasan'));
    checks.push({ name: 'Fuentes realmente renderizadas (Chrome DevTools Protocol)', title: rendered.fonts, body: bodyFonts.fonts });
    const resources = await page.evaluate(() => performance.getEntriesByType('resource').map(item => item.name));
    assert.equal(resources.some(url => /dashboard-.*\.js/.test(url)), false);
    assert.equal(resources.filter(url => url.endsWith('.woff2')).length, 3);
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('.skip-link').evaluate(element => element === window.document.activeElement), true);
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('main').evaluate(element => element === window.document.activeElement), true);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: /Menú/ }).click();
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#site-navigation').isVisible(), false);
    await shot('01-inicio-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await open('/proyectos/proyecto-sala-t');
    const first = page.locator('[data-gallery-image]').first();
    await first.focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#project-lightbox').isVisible(), true);
    await page.keyboard.press('ArrowRight');
    assert.match(await page.locator('[data-gallery-caption]').innerText(), /imagen 2 de/);
    await shot('02-galeria-teclado');
    await page.keyboard.press('Escape');
    assert.equal(await first.evaluate(element => element === window.document.activeElement), true);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await open('/');
    const motion = await page.locator('.home-hero__figure > img').evaluate(element => getComputedStyle(element).animationName);
    assert.equal(motion, 'none');
    await shot('03-movimiento-reducido');
    await page.emulateMedia({ reducedMotion: 'no-preference' });

    await login(state.email, state.password);
    await open('/admin');
    assert.equal(await page.locator('.chart-frame:visible').count(), 2);
    await page.getByText('Datos de solicitudes por semana', { exact: true }).click();
    assert.equal(await page.locator('.insights-charts details').first().getAttribute('open'), '');
    await shot('04-dashboard-tabla');
    await page.setViewportSize({ width: 390, height: 844 });
    await shot('05-dashboard-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await open('/admin/equipo');
    await shot('06-equipo');
    await open('/admin/equipo/create');
    const email = `gestor-navegador-${Date.now()}@example.test`;
    const password = 'Revision-gestor-12345';
    await page.locator('#name').fill('Gestor del recorrido de revisión');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill('Confirmación-incorrecta');
    await page.locator('#reason').fill('Alta ficticia para comprobar formularios reales');
    await submit(page.getByRole('button', { name: 'Crear gestor', exact: true }));
    assert.ok(await page.getByRole('alert').count());
    await shot('07-error-confirmacion');
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await submit(page.getByRole('button', { name: 'Crear gestor', exact: true }));
    const managerPath = new URL(page.url()).pathname;
    assert.match(managerPath, /^\/admin\/equipo\/\d+$/);
    assert.equal(await page.getByRole('status').count(), 1);
    await shot('08-consulta-gestor');
    await page.setViewportSize({ width: 390, height: 844 });
    await shot('09-consulta-gestor-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    const managerContext = await browser.newContext();
    const managerPage = await managerContext.newPage();
    await managerPage.goto(base + '/ingresar');
    await managerPage.locator('#email').fill(email);
    await managerPage.locator('#password').fill(password);
    await managerPage.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await managerPage.waitForURL('**/gestion');
    managerPage.on('dialog', dialog => dialog.accept());
    const claim = managerPage.locator('form').filter({ has: managerPage.locator('input[name="action"][value="claim"]') }).first();
    if (await claim.count()) {
        await claim.locator('[name="reason"]').fill('Tomar una solicitud ficticia para comprobar la reasignación al desactivar');
        await claim.getByRole('button', { name: 'Tomar solicitud', exact: true }).click();
        await managerPage.waitForLoadState('networkidle');
        await open(managerPath);
    }
    // Restablecimiento explícito y pérdida efectiva de una sesión que ya estaba abierta.
    await page.getByText('Restablecer contraseña del gestor', { exact: true }).click();
    await page.locator('#current_password').fill(state.password);
    await page.locator('#password').fill('Nueva-revision-12345');
    await page.locator('#password_confirmation').fill('Nueva-revision-12345');
    await page.locator('#password_reason').fill('Restablecimiento ficticio para verificar revocación de sesiones');
    await submit(page.getByRole('button', { name: 'Restablecer contraseña', exact: true }));
    assert.equal((await managerPage.reload()).status(), 403);
    await managerContext.close();
    if (await page.locator('#replacement_id').count()) {
        const replacement = await page.locator('#replacement_id option').filter({ hasText: 'Equipo ARAT' }).getAttribute('value');
        await page.locator('#replacement_id').selectOption(replacement);
    }
    await page.locator('#status_reason').fill('Desactivación de una cuenta ficticia sin expedientes abiertos');
    await submit(page.getByRole('button', { name: /^(Desactivar cuenta|Reasignar y desactivar)$/ }));
    assert.ok(await page.getByRole('button', { name: 'Reactivar cuenta', exact: true }).count());
    checks.push({ name: 'Alta, error de confirmación, restablecimiento con contraseña de administrador, revocación de sesión y desactivación', passed: true });
    await open('/admin/proyectos/3');
    await shot('10-galeria-administracion');
    await page.setViewportSize({ width: 390, height: 844 });
    await shot('11-galeria-administracion-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await open('/admin/proyectos/3/edit');
    await shot('12-seleccion-destacados');
    await context.clearCookies();
    await login(state.manager_email, state.manager_password);
    await shot('13-gestion-ambito-gestor');
    await context.clearCookies();
    await login(state.empty_email, state.manager_password);
    assert.ok(await page.getByText('Todavía no hay solicitudes recibidas en este período.', { exact: true }).count());
    await shot('14-indicadores-vacios');

    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await noJs.newPage();
    await fallback.goto(base + '/proyectos/proyecto-sala-t');
    assert.ok(await fallback.locator('[data-gallery-image]').first().isVisible());
    assert.match(await fallback.locator('[data-gallery-image]').first().getAttribute('href'), /\.webp$/);
    await fallback.goto(base + '/ingresar');
    await fallback.locator('#email').fill(state.email);
    await fallback.locator('#password').fill(state.password);
    await fallback.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await fallback.waitForURL('**/admin');
    assert.equal(await fallback.locator('.insights-charts details[open]').count(), 2);
    assert.equal(await fallback.locator('.chart-frame:visible').count(), 0);
    await fallback.screenshot({ path: `${directory}/15-sin-javascript.png`, fullPage: true });
    await noJs.close();
    checks.push({ name: 'Contenido, enlaces a originales y tablas accesibles sin JavaScript', passed: true });
    assert.deepEqual(errors, []);
} finally {
    await writeFile(`${directory}/checks.json`, JSON.stringify({ browser: await browser.version(), checks, errors }, null, 2));
    console.log(JSON.stringify({ checks: checks.length, errors }, null, 2));
    await browser.close();
}
