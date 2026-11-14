import { readdir, writeFile, access } from 'node:fs/promises';
import { resolve } from 'node:path';

const files = (await readdir('docs/design/captures/review')).filter(name => name.endsWith('.png')).sort();
const pairs = [
    ['Inicio · escritorio', '01-inicio-escritorio'], ['Inicio · móvil', '02-inicio-movil'],
    ['Expediente · escritorio', '03-expediente-escritorio'], ['Expediente · móvil', '04-expediente-movil'],
];
const figure = (src, title) => `<figure><a href="${src}"><img src="${src}" alt="${title}" loading="lazy"></a><figcaption>${title} · abrir imagen completa</figcaption></figure>`;
const html = `<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ARAT STUDIO · evidencia visual</title><style>
*{box-sizing:border-box}body{margin:0;background:#f6f5f1;color:#2e2f36;font:16px/1.6 system-ui,sans-serif}main{max-width:1280px;padding:40px 24px;margin:auto}h1{font-size:clamp(32px,5vw,56px);font-weight:450;letter-spacing:-.04em}h2{margin-top:56px}a{color:#324853;text-underline-offset:4px}figure{margin:0;min-width:0;background:white;padding:16px;border:1px solid #d9dfdf}img{display:block;width:100%;height:440px;object-fit:contain;object-position:top}figcaption{font-size:14px;margin-top:16px}.pair{display:grid;grid-template-columns:1fr 1fr;gap:24px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}.note{padding:24px;background:white;border-left:4px solid #324853}summary{cursor:pointer;min-height:44px;padding:12px 0}@media(max-width:650px){.pair{grid-template-columns:1fr}}
</style><main><p>ARAT STUDIO / Revisión del 14 de noviembre de 2026</p><h1>El proyecto, antes y después.</h1>
<p><a href="ENTREGA.md">Leer entrega y resultados</a> · <a href="captures/review/cotizacion-demostracion.pdf">Abrir PDF generado</a></p>
<p class="note">Capturas reales con datos ficticios. En el antes, Bootstrap no cargó desde el CDN en este entorno y algunas imágenes diferidas no aparecían aún en la captura integral. El después usa los estilos compilados del proyecto. Las imágenes se muestran completas; pulsa para ampliarlas.</p>
${pairs.map(([label, name]) => `<section><h2>${label}</h2><div class="pair">${figure(`captures/before/${name}.png`, 'Antes')}${figure(`captures/after/${name}.png`, 'Después')}</div></section>`).join('')}
<h2>Revisión del resto de pantallas</h2><p>Incluye escritorio, móvil, proceso comercial, estados y errores. El fallo de conexión de la captura 35 fue provocado para probar la recuperación.</p>
<div class="grid">${files.map(file => figure(`captures/review/${file}`, file.replace('.png','').replaceAll('-',' '))).join('')}</div>
<h2>Registros de comprobación</h2><ul><li><a href="captures/review/checks.json">Recorrido y pantallas</a></li><li><a href="captures/review/checks-states.json">Observaciones, vencimiento y versiones</a></li><li><a href="captures/review/checks-interactions.json">Errores, teclado y anchos</a></li><li><a href="contrast.json">Contraste de la paleta</a></li></ul></main></html>`;
await writeFile('docs/design/index.html', html);
const targets = [...html.matchAll(/(?:href|src)="([^"]+)"/g)].map(match => match[1]);
await Promise.all(targets.map(target => access(resolve('docs/design', target))));
console.log(`Galería creada con ${files.length} capturas de revisión y cuatro comparaciones.`);
console.log(`${targets.length} enlaces locales comprobados.`);
