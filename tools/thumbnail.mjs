import sharp from 'sharp';

// PHP envía rutas ya validadas por stdin; no se usa una shell ni se cambian originales.
let input = '';
for await (const chunk of process.stdin) input += chunk;
const jobs = JSON.parse(input);
if (!Array.isArray(jobs) || jobs.length > 200) throw Error('Lote de miniaturas no válido');
for (let index = 0; index < jobs.length; index += 2) {
    await Promise.all(jobs.slice(index, index + 2).map(job => sharp(job.source, { limitInputPixels: 40000000 })
        .rotate()
        .resize({ width: 480, withoutEnlargement: true })
        .webp({ quality: 80 })
        .toFile(job.destination)));
}
process.stdout.write(JSON.stringify({ thumbnails: jobs.length }));
