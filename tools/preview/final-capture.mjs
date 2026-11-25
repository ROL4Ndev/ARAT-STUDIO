import { chromium } from 'playwright-core';
import { mkdir, readFile, access, writeFile } from 'node:fs/promises';

const phase = process.argv[2] || 'after';
if (!['before', 'after'].includes(phase)) throw Error('Fase inválida');
const directory = `docs/final/captures/${phase}`;
if (phase === 'before') {
    try { await access(`${directory}/checks.json`); throw Error('La referencia inicial ya existe.'); }
    catch (error) { if (error.code !== 'ENOENT') throw error; }
}
await mkdir(directory, { recursive: true });
const state = JSON.parse(await readFile(`storage/app/${process.env.ARAT_PREVIEW_NAME || 'design-preview-v3'}/state.json`, 'utf8'));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const errors = [];
const checks = [];
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE', reducedMotion: 'reduce' });
    page.on('pageerror', error => errors.push(error.message));
    const capture = async (path, name) => {
        const file = `${directory}/${name}.png`;
        if (phase === 'before') {
            try { await access(file); return; } catch (error) { if (error.code !== 'ENOENT') throw error; }
        }
        const response = await page.goto(`http://127.0.0.1:8765${path}`, { waitUntil: 'networkidle' });
        if (response.status() !== 200) throw Error(`${path}: ${response.status()}`);
        await page.evaluate(async () => {
            document.querySelectorAll('img[src]').forEach(img => img.loading = 'eager');
            await Promise.all([...document.images].filter(img => img.hasAttribute('src')).map(img => img.decode().catch(() => {})));
            await document.fonts.ready;
        });
        const health = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > innerWidth,
            brokenImages: [...document.images].filter(img => img.hasAttribute('src') && !img.naturalWidth).length,
        }));
        checks.push({ path, name, ...health });
        await page.screenshot({ path: file, fullPage: true });
    };
    for (const [path, name] of [['/', 'inicio'], ['/nosotros', 'nosotros'], ['/servicios', 'servicios'], ['/proyectos', 'portafolio'], ['/proyectos/proyecto-sala-t', 'detalle'], ['/cotizador', 'cotizador'], ['/solicitar-proyecto', 'solicitud'], ['/acceder-expediente', 'acceso']]) {
        await capture(path, name);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    for (const [path, name] of [['/', 'inicio-movil'], ['/nosotros', 'nosotros-movil'], ['/servicios', 'servicios-movil'], ['/proyectos', 'portafolio-movil'], ['/cotizador', 'cotizador-movil'], ['/solicitar-proyecto', 'solicitud-movil'], ['/acceder-expediente', 'acceso-movil']]) await capture(path, name);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('http://127.0.0.1:8765/ingresar');
    await page.locator('#email').fill(state.email);
    await page.locator('#password').fill(state.password);
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await page.waitForURL('**/admin');
    for (const [path, name] of [['/admin', 'panel'], ['/gestion', 'gestion'], ['/admin/proyectos', 'administracion']]) await capture(path, name);
    await writeFile(`${directory}/checks.json`, JSON.stringify({ checks, errors }, null, 2));
    console.log(JSON.stringify({ captures: checks.length, errors, failures: checks.filter(check => check.overflow || check.brokenImages) }));
} finally { await browser.close(); }
