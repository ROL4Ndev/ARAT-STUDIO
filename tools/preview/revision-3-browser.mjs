import { chromium } from 'playwright-core';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:8765';
const output = process.env.ARAT_SCREENSHOT_DIR || 'docs/revision-3/captures';
const state = JSON.parse(await readFile(`storage/app/${process.env.ARAT_PREVIEW_NAME || 'design-preview-v3'}/state.json`, 'utf8'));
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const errors = [];
const checks = [];
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('dialog', dialog => dialog.accept());
const open = async path => {
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200, path);
};
const submit = async locator => {
    await locator.click();
    await page.waitForLoadState('networkidle');
};
const shot = async (target, name) => {
    await target.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await target.evaluate(async () => {
        document.querySelectorAll('img[src]').forEach(image => image.loading = 'eager');
        await Promise.all([...document.images].filter(image => image.hasAttribute('src')).map(image => image.decode().catch(() => {})));
        await document.fonts.ready;
    });
    await target.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth);
    const health = await target.evaluate(() => ({
        brokenImages: [...document.images].filter(image => image.hasAttribute('src') && !image.naturalWidth).length,
        overflow: document.documentElement.scrollWidth > innerWidth,
    }));
    assert.equal(health.brokenImages, 0, name);
    await target.screenshot({ path: `${output}/${name}.png`, fullPage: true });
    checks.push({ name, ...health });
};
const playingCount = target => target.locator('video').evaluateAll(videos => videos.filter(video => !video.paused).length);
try {
    const videoRequests = [];
    page.on('request', request => { if (request.url().endsWith('.mp4')) videoRequests.push(new URL(request.url()).pathname); });
    await open('/');
    await page.waitForFunction(() => document.querySelector('#home-interior-video').currentTime > .15);
    assert.equal(await playingCount(page), 1);
    assert.equal(videoRequests.some(url => url.includes('estudio-arthouse')), false);
    const dimensions = await page.locator('video').first().evaluate(video => ({ width: video.videoWidth, height: video.videoHeight, duration: video.duration, muted: video.muted }));
    assert.equal(dimensions.width, 1280);
    assert.equal(dimensions.height, 720);
    assert.equal(dimensions.muted, true);
    await shot(page, '01-inicio-escritorio');
    assert.equal(await page.locator('#proyectos .project-card').count(), Number(process.env.ARAT_EXPECT_FEATURED || 0));
    await page.getByRole('button', { name: 'Pausar video: interior referencial', exact: true }).focus();
    await page.keyboard.press('Space');
    const pausedAt = await page.locator('#home-interior-video').evaluate(video => video.currentTime);
    await page.waitForTimeout(300);
    assert.equal(await page.locator('#home-interior-video').evaluate(video => video.paused), true);
    assert.ok(Math.abs(await page.locator('#home-interior-video').evaluate(video => video.currentTime) - pausedAt) < .1);
    await page.getByRole('button', { name: 'Reproducir video: interior referencial', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('#home-interior-video').paused);
    await page.locator('#sobre').evaluate(section => section.scrollIntoView({ block: 'start', behavior: 'instant' }));
    await page.waitForFunction(() => !document.querySelector('#studio-process-video').paused);
    assert.equal(await playingCount(page), 1);
    assert.equal(await page.locator('#home-interior-video').getAttribute('src'), null);
    await page.locator('footer').scrollIntoViewIfNeeded();
    await page.waitForFunction(() => [...document.querySelectorAll('video')].every(video => video.paused && !video.hasAttribute('src')));
    checks.push({ name: 'MP4 reales decodificados, pausa/reanudación, un video activo y retirada de fuente al salir', dimensions, passed: true });
    await open('/nosotros'); await shot(page, '02-nosotros-escritorio');
    await open('/servicios'); await shot(page, '03-servicios-escritorio');
    assert.equal(await page.locator('.service-story__figure').count(), 4);
    await open('/proyectos?page=2');
    assert.equal(await page.locator('.project-card').count(), 4);
    assert.equal(await page.locator('[data-filter-submit]').isVisible(), false);
    await page.locator('#category').selectOption('diseno-interior');
    await page.waitForURL('**/proyectos?category=diseno-interior');
    assert.equal(new URL(page.url()).searchParams.has('page'), false);
    assert.equal(await page.locator('.project-card').count(), 4);
    await page.goBack({ waitUntil: 'networkidle' });
    assert.equal(new URL(page.url()).searchParams.get('page'), '2');
    await page.goForward({ waitUntil: 'networkidle' });
    assert.equal(new URL(page.url()).searchParams.get('category'), 'diseno-interior');
    await shot(page, '04-portafolio-filtrado');
    checks.push({ name: 'Filtro GET automático, reinicio de página y navegación atrás/adelante', passed: true });
    await open('/proyectos'); await shot(page, '05-portafolio-todos');
    await open('/proyectos?page=2');
    const firstCategory = await page.locator('#category option').nth(1).getAttribute('value');
    await page.locator('#category').focus();
    await page.keyboard.press('ArrowDown');
    await page.waitForURL(url => url.searchParams.get('category') === firstCategory && !url.searchParams.has('page'));
    checks.push({ name: 'Selector de categoría operado con teclado y control de pausa con Espacio', passed: true });
    for (const [name, path] of [['inicio', '/'], ['nosotros', '/nosotros'], ['servicios', '/servicios'], ['portafolio', '/proyectos']]) {
        await page.setViewportSize({ width: 390, height: 844 });
        await open(path); await shot(page, `06-${name}-movil`);
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await open('/ingresar');
    await page.locator('#email').fill(state.email); await page.locator('#password').fill(state.password);
    await submit(page.getByRole('button', { name: 'Ingresar', exact: true }));
    await open('/admin/proyectos');
    for (const label of ['Publicado', 'Destacado en inicio', 'Orden en inicio']) assert.ok(await page.getByRole('columnheader', { name: label, exact: true }).count());
    assert.equal(await page.getByRole('link', { name: 'Administrar galería', exact: true }).count(), 13);
    await shot(page, '07-administracion-proyectos');
    for (const [id, order] of [[1, 2], [2, 1]]) {
        await open(`/admin/proyectos/${id}/edit#seleccion-inicio`);
        await page.locator('#is_featured').check();
        await page.locator('#featured_order').fill(String(order));
        await page.locator('#reason').fill('Selección editorial de demostración para verificar el orden de inicio');
        await submit(page.getByRole('button', { name: /Guardar cambios/ }));
    }
    await open('/admin/proyectos/3/edit#seleccion-inicio');
    await page.locator('#is_featured').uncheck();
    await page.locator('#reason').fill('Retirar de destacados sin despublicar el proyecto de demostración');
    await submit(page.getByRole('button', { name: /Guardar cambios/ }));
    await open('/');
    const titles = await page.locator('.project-card__title').allTextContents();
    assert.match(titles[0], /Proyecto OPV/); assert.match(titles[1], /Proyecto Sala T/);
    assert.equal(titles.length, 2);
    await shot(page, '15-destacados-seleccionados-en-ensayo');
    await open('/admin/proyectos/1/edit#seleccion-inicio'); await shot(page, '08-editar-seleccion');
    await open('/admin/proyectos/1#galeria');
    assert.equal(await page.locator('.admin-gallery-item').count(), 4);
    await shot(page, '09-galeria-administrativa');
    checks.push({ name: 'Selección y orden mediante formularios reales; galería conservada; datos exclusivamente de ensayo', passed: true });

    const reduced = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } });
    const reducedPage = await reduced.newPage();
    const reducedRequests = [];
    reducedPage.on('request', request => { if (request.url().endsWith('.mp4')) reducedRequests.push(request.url()); });
    await reducedPage.goto(base, { waitUntil: 'networkidle' });
    await reducedPage.locator('[data-film-toggle]').first().waitFor({ state: 'visible' });
    assert.equal(reducedRequests.length, 0);
    assert.equal(await reducedPage.locator('[data-studio-film]').first().getAttribute('data-render'), 'poster');
    await shot(reducedPage, '10-movimiento-reducido');
    await reduced.close();

    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await noJs.newPage();
    const fallbackRequests = [];
    fallback.on('request', request => { if (request.url().endsWith('.mp4')) fallbackRequests.push(request.url()); });
    await fallback.goto(base + '/proyectos?page=2');
    assert.equal(await fallback.locator('[data-filter-submit]').isVisible(), true);
    await fallback.locator('#category').selectOption('diseno-interior');
    await fallback.getByRole('button', { name: 'Filtrar', exact: true }).click();
    await fallback.waitForURL('**/proyectos?category=diseno-interior');
    assert.equal(await fallback.locator('.project-card').count(), 4);
    await shot(fallback, '11-filtro-sin-javascript');
    await fallback.goto(base);
    assert.equal(await fallback.locator('[data-film-toggle]').first().isVisible(), false);
    assert.equal(fallbackRequests.length, 0);
    await shot(fallback, '12-inicio-sin-javascript');
    await noJs.close();
    checks.push({ name: 'Movimiento reducido y ausencia de JS conservan posters sin descargar MP4; filtro GET funcional', passed: true });

    const blocked = await browser.newContext();
    await blocked.addInitScript(() => {
        const original = HTMLMediaElement.prototype.play;
        HTMLMediaElement.prototype.play = function () {
            if (!window.allowVideoGesture) return Promise.reject(new DOMException('Bloqueo simulado de autoplay', 'NotAllowedError'));
            return original.call(this);
        };
    });
    const blockedPage = await blocked.newPage();
    await blockedPage.goto(base);
    await blockedPage.waitForFunction(() => document.querySelector('[data-studio-film]').dataset.state === 'blocked');
    assert.equal(await blockedPage.locator('[data-studio-film]').first().getAttribute('data-render'), 'poster');
    await shot(blockedPage, '13-autoplay-bloqueado');
    await blockedPage.evaluate(() => window.allowVideoGesture = true);
    await blockedPage.getByRole('button', { name: 'Reproducir video: interior referencial', exact: true }).click();
    await blockedPage.waitForFunction(() => !document.querySelector('#home-interior-video').paused);
    await blocked.close();
    checks.push({ name: 'Bloqueo de autoplay simulado: poster y reproducción manual del MP4 real', passed: true });

    const failing = await browser.newContext();
    let refuseVideo = true;
    await failing.route('**/inicio-charlotte-may.mp4', route => refuseVideo
        ? route.fulfill({ status: 404, body: '', headers: { 'Cache-Control': 'no-store' } }) : route.continue());
    const failurePage = await failing.newPage();
    await failurePage.goto(base);
    await failurePage.waitForFunction(() => document.querySelector('[data-studio-film]').dataset.state === 'error');
    assert.equal(await failurePage.locator('[data-studio-film]').first().getAttribute('data-render'), 'poster');
    await shot(failurePage, '14-video-no-disponible');
    refuseVideo = false;
    await failurePage.getByRole('button', { name: 'Reproducir video: interior referencial', exact: true }).click();
    await failurePage.waitForFunction(() => document.querySelector('#home-interior-video').currentTime > .15);
    await failing.close();
    checks.push({ name: 'Fallo HTTP 404 inyectado: poster real, mensaje accesible y reintento satisfactorio', passed: true });
    assert.deepEqual(errors, []);
} finally {
    await writeFile(`${output}/checks.json`, JSON.stringify({ browser: await browser.version(), checks, errors }, null, 2));
    console.log(JSON.stringify({ checks: checks.length, errors }, null, 2));
    await browser.close();
}
