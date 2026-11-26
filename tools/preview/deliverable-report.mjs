import { readFile, writeFile, readdir } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import assert from 'node:assert/strict';

const root = 'docs/tercera-presentacion';
const read = async name => JSON.parse((await readFile(`${root}/${name}`, 'utf8')).replace(/^\uFEFF/, ''));
const [browser, concurrency, commercial, accessibility, pdfs, data, source] = await Promise.all([
    read('browser.json'), read('concurrency.json'), read('commercial-concurrency.json'), read('accessibility.json'), read('pdfs.json'), read('data-verification.json'), read('source-map.json'),
]);
const xml = await readFile('storage/framework/testing/third-presentation.xml', 'utf8');
const suite = xml.match(/<testsuite\s[^>]+>/)[0];
const phpunit = Object.fromEntries([...suite.matchAll(/(tests|assertions|errors|failures|skipped)="(\d+)"/g)].map(m => [m[1], Number(m[2])]));
assert.equal(phpunit.tests, 132); assert.equal(phpunit.errors + phpunit.failures + phpunit.skipped, 0);
assert.equal(browser.completed, true); assert.deepEqual(browser.errors, []);
assert.equal(concurrency.status, 'passed'); assert.equal(Object.keys(concurrency.checks).length, 6);
assert.equal(Object.keys(commercial.results).length, 10);
assert.deepEqual(accessibility.errors, []); assert.equal(data.verified && data.demo_repeat_unchanged, true);
for (const row of source) assert.equal(createHash('sha256').update(await readFile(row.file)).digest('hex'), row.sha256, row.file);
const captures = (await readdir(`${root}/capturas`)).filter(name => name.endsWith('.png')).sort();
assert.equal(captures.length, 27);
const serverLog = await readFile('storage/app/private/third-presentation/preview-error.log', 'utf8');
const serverErrors = Object.fromEntries(['PHP Fatal', 'PHP Warning', 'PHP Notice', 'Uncaught', 'SQLSTATE'].map(term => [term, serverLog.split(term).length - 1]));
assert.equal(Object.values(serverErrors).some(Boolean), false);
let teardown = null;
try { teardown = await read('teardown.json'); } catch (error) { if (error.code !== 'ENOENT') throw error; }
const summary = { date: '2026-11-25', timezone: 'America/Lima', base: '7436dd5', branch: 'main', phpunit,
    newFeatureTests: 13, mariadbDocumentRaces: 6, mariadbCommercialChecks: 10, build: 'passed', phpLint: { files: 201, errors: 0 },
    blade: 'compiled_and_cleared', newRoutes: 8, migration: '2026_11_25_220000_create_deliverables',
    browser: { completed: true, version: browser.browser, checks: browser.checks, workflowCaptures: browser.captures.length, confirmations: browser.confirmations },
    accessibility, nativePdfViews: pdfs.checks.length, captures: captures.length,
    preservedRows: data.original_rows_unchanged, preservedFiles: data.original_files_unchanged,
    demoDeliverables: data.deliverables.length, demoVersions: data.version_files.length, demoIdempotent: true,
    sourceReferences: source.length, serverErrors, teardown };
await writeFile(`${root}/checks-summary.json`, JSON.stringify(summary, null, 2));
const descriptions = {
    '01-gestor-borrador': 'Gestor: archivo guardado como borrador privado',
    '02-gestor-borrador-360': 'Gestor: borrador en móvil de 360 px',
    '03-gestor-enviado': 'Gestor: versión 1 enviada a revisión',
    '04-cliente-revision-v1': 'Cliente: primera versión disponible para descargar y revisar',
    '05-cliente-validacion': 'Cliente: comentario obligatorio y error junto al campo',
    '06-cliente-observado': 'Cliente: observación registrada sobre la versión 1',
    '07-gestor-observaciones': 'Gestor: consulta de observación e historial',
    '08-gestor-borrador-v2': 'Gestor: segunda versión en borrador',
    '09-cliente-revision-v2': 'Cliente: segunda versión enviada y primera conservada',
    '10-cliente-conforme': 'Cliente: conformidad y respuestas por versión',
    '11-cliente-conforme-390': 'Cliente: conformidad en móvil de 390 px',
    '11-cliente-conforme-360': 'Cliente: conformidad en móvil de 360 px',
    '11-cliente-conforme-768': 'Cliente: conformidad en pantalla de 768 px',
    '12-gestor-conformidad': 'Gestor: entregable finalizado y de solo lectura',
    '13-administrador': 'Administrador: consulta autorizada del entregable',
    '15-gestor-ajeno-denegado': 'Gestor ajeno: acceso denegado',
    '16-pdf-abierto': 'Visor PDF: documento descargado de la versión 2',
    '17-formulario-teclado-390': 'Formulario: navegación con Tab y foco visible',
    '18-formulario-sin-js-360': 'Formulario: respaldo sin JavaScript',
    '19-pdf-pendiente': 'PDF del escenario habitual: pendiente de respuesta',
    '20-pdf-revision-v1': 'PDF del escenario habitual: primera versión observada',
    '21-pdf-revision-v2': 'PDF del escenario habitual: segunda versión enviada',
    '22-pdf-conforme': 'PDF del escenario habitual: conforme',
};
for (const kind of ['proyectos', 'categorias', 'servicios', 'tarifas']) descriptions[`14-catalogo-${kind}`] = `Administrador: catálogo de ${kind}`;
const ordered = [...browser.captures.map(name => `${name}.png`), ...captures.filter(name => !browser.captures.includes(name.replace(/\.png$/, '')))];
const items = ordered.map(file => ({ file, title: descriptions[file.replace(/\.png$/, '')] || file }));
const escape = value => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;');
const links = items.map((item, i) => `<li><a href="capturas/${item.file}" data-capture="${i}">${escape(item.title)}</a></li>`).join('\n');
const html = `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ARAT STUDIO · tercera presentación</title><style>
*{box-sizing:border-box}body{margin:0;font:16px/1.6 system-ui,sans-serif;color:#2e2f36;background:#edf2f3}
header{background:#324853;color:#fff;padding:28px max(20px,4vw)}header h1{font-size:clamp(1.6rem,3vw,2.7rem);font-weight:500;margin:8px 0}header a{color:#fff;margin-right:24px}
main{display:grid;grid-template-columns:330px minmax(0,1fr);gap:24px;padding:24px}nav{background:#fff;padding:20px;align-self:start}ol{padding-left:24px}li{margin:12px 0}a{color:#324853;text-underline-offset:3px}a[aria-current=true]{font-weight:700}
figure{margin:0;min-width:0}figcaption{padding:16px;background:#cedee2;font-weight:600}figure img{display:block;max-width:100%;height:auto;margin:auto;background:#fff}figure>a{display:block;background:#fff}
button{min-height:44px;padding:8px 18px;background:#fff;border:1px solid #6f858e;color:#324853;font:inherit}button:disabled{opacity:.6}.controls{display:flex;gap:16px;padding:16px 0}button:focus-visible,a:focus-visible{outline:3px solid #226786;outline-offset:3px}
.stats{display:flex;gap:24px;flex-wrap:wrap;margin-top:20px}.stats span{padding:8px 16px;border:1px solid #b9d1d9}.note{padding:0 24px 32px}
@media(max-width:900px){main{grid-template-columns:1fr;padding:16px}nav{max-height:240px;overflow:auto}}
</style></head><body><header><p>ARAT STUDIO · 25 de noviembre de 2026</p><h1>Entrega y revisión de documentos del servicio</h1>
<p>Borrador → envío → observación → nueva versión → conformidad. Capturas reales de sesiones aisladas.</p>
<a href="README.md">Guía y demostración</a><a href="BLOQUES-TECNICOS.md">Cuatro bloques técnicos</a><a href="RESULTADOS.md">Verificación y límites</a>
<div class="stats"><span>132 pruebas / 1.395 aserciones</span><span>16 comprobaciones MariaDB</span><span>27 capturas</span></div></header>
<main><nav aria-label="Pantallas del recorrido"><ol>${links}</ol></nav><div><div class="controls"><button id="previous" type="button">Anterior</button><button id="next" type="button">Siguiente</button></div>
<figure><figcaption id="caption">${escape(items[0].title)}</figcaption><a id="original" href="capturas/${items[0].file}"><img id="capture" src="capturas/${items[0].file}" alt="${escape(items[0].title)}"></a></figure></div></main>
<p class="note">Abrir una imagen muestra su tamaño original. Las contraseñas, tokens y respaldos no se incluyen en esta galería. Los documentos de archivos/ son ficticios y están marcados como demostración académica.</p>
<script>const items=${JSON.stringify(items)};let current=0;function show(index){current=index;const item=items[current];document.getElementById('caption').textContent=item.title;document.getElementById('capture').src='capturas/'+item.file;document.getElementById('capture').alt=item.title;document.getElementById('original').href='capturas/'+item.file;document.getElementById('previous').disabled=current===0;document.getElementById('next').disabled=current===items.length-1;document.querySelectorAll('[data-capture]').forEach(a=>a.setAttribute('aria-current',String(Number(a.dataset.capture)===current)))}document.querySelectorAll('[data-capture]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();show(Number(a.dataset.capture))}));document.getElementById('previous').addEventListener('click',()=>show(current-1));document.getElementById('next').addEventListener('click',()=>show(current+1));show(0);</script></body></html>`;
await writeFile(`${root}/index.html`, html);
console.log(JSON.stringify({ tests: phpunit.tests, assertions: phpunit.assertions, captures: captures.length, references: source.length }));
