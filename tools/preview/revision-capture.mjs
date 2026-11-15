import { chromium } from 'playwright-core';
import { readFile, mkdir, access } from 'node:fs/promises';

const phase = process.argv[2] || 'after';
if (!['before', 'after'].includes(phase)) throw Error('Fase no válida');
const directory = `docs/revision-2/captures/${phase}`;
if (phase === 'before') {
    try { await access(`${directory}/01-inicio.png`); throw Error('La evidencia inicial ya existe; no se sobrescribe.'); }
    catch (error) { if (error.code !== 'ENOENT') throw error; }
}
await mkdir(directory, { recursive: true });
const state = JSON.parse(await readFile(`storage/app/${process.env.ARAT_PREVIEW_NAME || 'design-preview'}/state.json`, 'utf8'));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
    const capture = async (path, name) => {
        const response = await page.goto('http://127.0.0.1:8765' + path, { waitUntil: 'networkidle' });
        if (response.status() !== 200) throw Error(`${path}: ${response.status()}`);
        await page.evaluate(async () => {
            document.querySelectorAll('img').forEach(img => img.loading = 'eager');
            await Promise.all([...document.images].map(img => img.decode().catch(() => {})));
        });
        await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
    };
    await capture('/', '01-inicio');
    await page.setViewportSize({ width: 390, height: 844 }); await capture('/', '02-inicio-movil');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await capture('/proyectos/proyecto-sala-t', '03-proyecto');
    await page.goto('http://127.0.0.1:8765/ingresar');
    await page.locator('#email').fill(state.email); await page.locator('#password').fill(state.password);
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click(); await page.waitForURL('**/admin');
    await capture('/admin', '04-dashboard'); await capture('/gestion', '05-gestion');
    await capture('/admin/proyectos', '06-proyectos');
    console.log(`Seis capturas ${phase} conservadas en ${directory}`);
} finally { await browser.close(); }
