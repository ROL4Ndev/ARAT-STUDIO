import { chromium } from 'playwright-core';
import { mkdir, writeFile, access } from 'node:fs/promises';

const phase = process.argv[2];
if (!['before', 'after'].includes(phase)) throw Error('Fase requerida: before o after.');
const directory = `docs/editorial-20261125/${phase}`;
await mkdir(directory, { recursive: true });
if (phase === 'before') {
    try { await access(`${directory}/checks.json`); throw Error('La referencia inicial ya existe.'); }
    catch (error) { if (error.code !== 'ENOENT') throw error; }
}
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [], errors = [];
try {
    const page = await browser.newPage({ locale: 'es-PE', reducedMotion: 'reduce' });
    page.on('pageerror', error => errors.push(error.message));
    const routes = [['/nosotros', 'nosotros'], ['/', 'inicio'], ['/servicios', 'servicios'], ['/proyectos', 'portafolio'], ['/proyectos/proyecto-sala-t', 'detalle'], ['/cotizador', 'cotizador'], ['/solicitar-proyecto', 'solicitud'], ['/acceder-expediente', 'acceso']];
    for (const width of [1440, 390, 768, 360]) {
        await page.setViewportSize({ width, height: width === 1440 ? 1000 : 844 });
        for (const [route, name] of routes) {
            const response = await page.goto(`http://127.0.0.1:8765${route}`, { waitUntil: 'networkidle' });
            await page.evaluate(async () => {
                document.querySelectorAll('img[src]').forEach(img => img.loading = 'eager');
                await Promise.all([...document.images].map(img => img.decode().catch(() => {})));
                await document.fonts.ready;
            });
            const health = await page.evaluate(() => ({
                overflow: document.documentElement.scrollWidth > innerWidth,
                brokenImages: [...document.images].filter(img => img.hasAttribute('src') && !img.naturalWidth).length,
            }));
            checks.push({ route, width, status: response.status(), ...health });
            await page.screenshot({ path: `${directory}/${name}-${width}.png`, fullPage: true });
        }
    }
    await writeFile(`${directory}/checks.json`, JSON.stringify({ checks, errors }, null, 2));
    console.log(JSON.stringify({ captures: checks.length, errors, failures: checks.filter(c => c.status !== 200 || c.overflow || c.brokenImages) }));
} finally { await browser.close(); }
