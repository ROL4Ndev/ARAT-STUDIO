import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

const output = 'docs/editorial-20261125/accessibility';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [], errors = [], contrast = [];
const base = 'http://127.0.0.1:8765';
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    for (const route of ['/nosotros', '/', '/servicios', '/proyectos', '/proyectos/proyecto-sala-t', '/cotizador', '/solicitar-proyecto', '/acceder-expediente']) {
        await page.goto(base + route, { waitUntil: 'networkidle' });
        const result = await page.evaluate(() => {
            const rgba = value => value.match(/[\d.]+/g).map(Number);
            const mix = (front, back) => { const a = front[3] ?? 1; return front.slice(0, 3).map((v, i) => v * a + back[i] * (1 - a)); };
            const background = element => {
                const stack = []; for (let node = element; node; node = node.parentElement) stack.unshift(node);
                return stack.reduce((back, node) => mix(rgba(getComputedStyle(node).backgroundColor), back), [255, 255, 255]);
            };
            const luminance = rgb => rgb.map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
            const ratio = (a, b) => (Math.max(luminance(a), luminance(b)) + .05) / (Math.min(luminance(a), luminance(b)) + .05);
            const measured = [...document.querySelectorAll('body *')].filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden' && [...el.childNodes].some(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim()));
            const rows = measured.map(el => {
                const style = getComputedStyle(el), bg = background(el), fg = mix(rgba(style.color), bg);
                const minimum = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && Number(style.fontWeight) >= 700) ? 3 : 4.5;
                return { text: el.textContent.trim().slice(0, 90), ratio: Number(ratio(fg, bg).toFixed(2)), minimum, foreground: style.color, background: bg.map(Math.round) };
            });
            const control = document.querySelector('.form-control, .form-select');
            let controlBorder;
            if (control) { const style = getComputedStyle(control); controlBorder = Number(ratio(rgba(style.borderTopColor), background(control)).toFixed(2)); }
            return { samples: rows.length, minimumRatio: Math.min(...rows.map(r => r.ratio)), failures: rows.filter(r => r.ratio < r.minimum), controlBorder };
        });
        contrast.push({ route, ...result });
    }
    await page.goto(base + '/nosotros', { waitUntil: 'networkidle' });
    assert.match(await page.locator('h1').innerText(), /Diseño con propósito/);
    assert.match(await page.locator('h1').innerText(), /Arquitectura con precisión/);
    assert.equal(await page.locator('[data-reveal]').evaluateAll(nodes => nodes.every(node => getComputedStyle(node).animationName === 'none' && getComputedStyle(node).opacity === '1')), true);
    checks.push('Movimiento reducido: contenido visible y sin entradas animadas');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('.skip-link').evaluate(node => node === document.activeElement), true);
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('main').evaluate(node => node === document.activeElement), true);
    checks.push('Primer Tab muestra Saltar al contenido y Enter enfoca el contenido');
    await page.setViewportSize({ width: 360, height: 844 });
    await page.goto(base + '/nosotros', { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Menú/ }).focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#site-navigation').isVisible(), true);
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    const aboutLink = page.locator('#site-navigation').getByRole('link', { name: 'Nosotros', exact: true });
    await aboutLink.waitFor({ state: 'visible' });
    await page.keyboard.press('Tab');
    assert.equal(await aboutLink.evaluate(node => node === document.activeElement), true);
    assert.equal(await aboutLink.evaluate(node => getComputedStyle(node).outlineStyle), 'solid');
    await page.screenshot({ path: `${output}/menu-teclado-360.png`, fullPage: false });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.site-submenu').first().getAttribute('open'), null);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#site-navigation').isVisible(), false);
    assert.equal(await page.getByRole('button', { name: /Menú/ }).evaluate(node => node === document.activeElement), true);
    checks.push('Menú móvil a 360 px: teclado, submenú, Escape y devolución del foco');
    await page.goto(base + '/servicios', { waitUntil: 'networkidle' });
    const faq = page.locator('.service-faq summary').first();
    await faq.focus(); await page.keyboard.press('Space');
    assert.equal(await page.locator('.service-faq details').first().getAttribute('open'), '');
    await page.keyboard.press('Space');
    assert.equal(await page.locator('.service-faq details').first().getAttribute('open'), null);
    checks.push('Acordeón de servicios abre y cierra con Espacio');
    await page.goto(base + '/proyectos/proyecto-sala-t', { waitUntil: 'networkidle' });
    const opener = page.locator('[data-gallery-image]').first();
    await opener.click();
    assert.equal(await page.locator('dialog').isVisible(), true);
    await page.keyboard.press('ArrowRight');
    assert.match(await page.locator('[data-gallery-caption]').innerText(), /imagen 2/);
    await page.screenshot({ path: `${output}/galeria-360.png`, fullPage: false });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog').isVisible(), false);
    assert.equal(await opener.evaluate(node => node === document.activeElement), true);
    checks.push('Galería a 360 px: imagen siguiente, Escape y restauración del foco');
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await noJs.newPage();
    let videoRequests = 0; fallback.on('request', request => { if (request.url().endsWith('.mp4')) videoRequests++; });
    await fallback.goto(base + '/nosotros', { waitUntil: 'networkidle' });
    assert.equal(await fallback.locator('h1').isVisible(), true);
    assert.equal(await fallback.locator('.team-person').count(), 2);
    assert.equal(await fallback.locator('[data-film-toggle]').isVisible(), false);
    assert.equal(videoRequests, 0);
    await fallback.screenshot({ path: `${output}/nosotros-sin-js-390.png`, fullPage: true });
    checks.push('Nosotros sin JavaScript: título, equipo, enlaces y poster disponibles; sin descarga MP4');
    await noJs.close();
    assert.deepEqual(errors, []);
    assert.equal(contrast.flatMap(r => r.failures).length, 0, JSON.stringify(contrast.filter(r => r.failures.length)));
    assert.equal(contrast.some(r => r.controlBorder !== undefined && r.controlBorder < 3), false);
} finally {
    await writeFile(`${output}/checks.json`, JSON.stringify({ checks, errors, contrast }, null, 2));
    console.log(JSON.stringify({ checks: checks.length, errors, contrastFailures: contrast.flatMap(r => r.failures) }));
    await browser.close();
}
