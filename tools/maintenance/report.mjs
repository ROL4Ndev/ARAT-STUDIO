import { readFile, writeFile, readdir } from 'node:fs/promises';

const xml = await readFile('storage/framework/testing/final.xml', 'utf8');
const attributes = xml.match(/<testsuite\s+[^>]*tests="(\d+)" assertions="(\d+)" errors="(\d+)" failures="(\d+)" skipped="(\d+)" time="([\d.]+)"/);
if (!attributes) throw Error('No hay resultado JUnit completo.');
const concurrency = JSON.parse(await readFile('storage/framework/testing/isolated-run.json', 'utf8'));
const data = JSON.parse(await readFile('docs/final/data-verification.json', 'utf8'));
const before = await readdir('docs/final/captures/before');
const after = await readdir('docs/final/captures/after');
const luminance = color => {
    const rgb = color.match(/[a-f0-9]{2}/gi).map(value => parseInt(value, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return rgb.reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
};
const colors = [['Texto', '#2e2f36', '#ffffff'], ['Texto secundario', '#5d666c', '#ffffff'], ['Texto secundario sobre neutro', '#5d666c', '#f6f5f1'], ['Botón principal', '#ffffff', '#324853'], ['Observación', '#785011', '#fff3d6']];
const contrast = colors.map(([label, foreground, background]) => {
    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return { label, foreground, background, ratio: Number(((values[0] + .05) / (values[1] + .05)).toFixed(2)) };
});
const summary = { php: '8.5.12', tests: Number(attributes[1]), assertions: Number(attributes[2]), errors: Number(attributes[3]), failures: Number(attributes[4]), skipped: Number(attributes[5]), seconds: Number(attributes[6]),
    concurrency_checks: Object.keys(concurrency.results), before_captures: before.filter(file => file.endsWith('.png')).length, after_captures: after.filter(file => file.endsWith('.png')).length,
    media: JSON.parse(await readFile('docs/final/media/checks.json', 'utf8')), workflow: JSON.parse(await readFile('docs/final/workflow/checks.json', 'utf8')), contrast };
await writeFile('docs/final/checks-summary.json', JSON.stringify(summary, null, 2));
const screens = [['inicio','Inicio'],['nosotros','Nosotros'],['servicios','Servicios'],['portafolio','Portafolio'],['detalle','Detalle de proyecto'],['cotizador','Cotizador'],['solicitud','Solicitud'],['acceso','Acceso al expediente'],['panel','Panel'],['gestion','Gestión'],['administracion','Administración']];
const sections = screens.map(([file, label]) => `<section id="${file}"><h2>${label}</h2><div class="comparison">${['before','after'].map((phase, index) => `<figure><figcaption>${index ? 'Después' : 'Antes'}</figcaption><a href="captures/${phase}/${file}.png"><img src="captures/${phase}/${file}.png" loading="lazy" alt="${label}, ${index ? 'después' : 'antes'}"></a></figure>`).join('')}</div>${after.includes(`${file}-movil.png`) ? `<p><a href="captures/after/${file}-movil.png">Ver móvil</a></p>` : ''}</section>`).join('');
await writeFile('docs/final/index.html', `<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ARAT STUDIO · Revisión final</title><style>body{margin:0;background:#f6f5f1;color:#2e2f36;font:16px/1.6 system-ui,sans-serif}main{max-width:1300px;margin:auto;padding:40px 24px}a{color:#324853}nav{display:flex;gap:20px;flex-wrap:wrap;padding:24px 0}section{padding:24px 0 48px;border-top:1px solid #c7ced0}.comparison{display:grid;grid-template-columns:1fr 1fr;gap:24px}figure{margin:0;min-width:0}figcaption{font-weight:600;padding:12px 0}img{width:100%;max-height:760px;object-fit:contain;object-position:top;background:#fff;border:1px solid #d9dfdf}a:focus-visible{outline:3px solid #324853;outline-offset:4px}@media(max-width:700px){.comparison{grid-template-columns:1fr}}</style><main><h1>ARAT STUDIO · Antes y después</h1><p>24 de noviembre de 2026. Capturas reales de Chrome local, con cuentas y expedientes ficticios en SQLite aislado. La base habitual conserva 16 solicitudes y cuatro cuentas del equipo.</p><p><a href="ENTREGA.md">Informe y recorrido</a> · <a href="workflow/05-expediente-cliente.png">Expediente del cliente</a> · <a href="workflow/07-aceptacion-y-cierre.png">Aceptación y cierre</a></p><nav aria-label="Pantallas">${screens.map(([id, label]) => `<a href="#${id}">${label}</a>`).join('')}</nav>${sections}<p>${summary.tests} pruebas y ${summary.assertions} aserciones. ${summary.concurrency_checks.length} comprobaciones MariaDB. Sin errores inesperados de JavaScript en los recorridos documentados.</p></main></html>`);
console.log(JSON.stringify({ tests: summary.tests, assertions: summary.assertions, contrast, duplicate_visual_groups_preserved: data.duplicate_visual_files.length }));
