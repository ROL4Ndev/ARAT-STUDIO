import { chromium } from 'playwright-core';
import { readFile, mkdir, writeFile } from 'node:fs/promises';

const phase = process.argv[2] || 'after';
if (phase !== 'after') throw new Error('Las capturas iniciales y piloto son evidencia conservada. Solo se puede regenerar la fase after con el código actual.');
const state = JSON.parse(await readFile('storage/app/design-preview/state.json', 'utf8'));
const directory = `docs/design/captures/${phase}`;
await mkdir(directory, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
const page = await context.newPage();
const errors = [];
const checks = [];
page.on('pageerror', error => errors.push(error.message));
const open = async path => {
    const response = await page.goto(`http://127.0.0.1:8765${path}`, { waitUntil: 'networkidle' });
    if (response.status() >= 400) throw new Error(`${path}: HTTP ${response.status()}`);
};
const capture = async name => {
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.evaluate(async () => {
        document.querySelectorAll('img').forEach(img => { img.loading = 'eager'; });
        await Promise.all([...document.images].map(img => img.decode().catch(() => {})));
    });
    await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
    checks.push({ name, url: new URL(page.url()).pathname, viewport: page.viewportSize(), ...await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images].filter(i => !i.complete || !i.naturalWidth).map(i => i.getAttribute('src')),
        heading: document.querySelector('h1')?.textContent.trim(),
    })) });
};
try {
    await open('/'); await capture('01-inicio-escritorio');
    await page.setViewportSize({ width: 390, height: 844 });
    await capture('02-inicio-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await open('/ingresar');
    await page.locator('#email').fill(state.email);
    await page.locator('#password').fill(state.password);
    await page.locator('button[type=submit]').click();
    await page.waitForURL('**/admin');
    await open(`/gestion/solicitudes/${state.request}`);
    const demoTools = page.getByText('Demostración local del acceso del cliente', { exact: true });
    if (await demoTools.count()) await demoTools.click();
    await page.getByRole('button', { name: 'Preparar acceso de cliente para demostración' }).click();
    await page.waitForLoadState('networkidle');
    const access = await page.locator('#demo-access-link').inputValue();
    // El cliente debe abrir el enlace sin una sesión interna.
    const client = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
    const clientPage = await client.newPage();
    await clientPage.goto(access); await clientPage.locator('button[type=submit]').click();
    await clientPage.waitForURL('**/mi-solicitud'); await clientPage.waitForLoadState('networkidle');
    await clientPage.screenshot({ path: `${directory}/03-expediente-escritorio.png`, fullPage: true });
    await clientPage.setViewportSize({ width: 390, height: 844 });
    await clientPage.screenshot({ path: `${directory}/04-expediente-movil.png`, fullPage: true });
    checks.push({ name: 'expediente-movil', overflow: await clientPage.evaluate(() => document.documentElement.scrollWidth > innerWidth) });
    await client.close();
    await writeFile(`${directory}/checks.json`, JSON.stringify({ checks, errors }, null, 2));
    console.log(JSON.stringify({ phase, captures: 4, checks, errors }, null, 2));
} finally { await browser.close(); }
