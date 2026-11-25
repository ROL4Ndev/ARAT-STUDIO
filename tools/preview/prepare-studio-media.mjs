import fs from 'node:fs/promises';
import path from 'node:path';
import http from 'node:http';
import { createReadStream } from 'node:fs';
import sharp from 'sharp';
import { chromium } from 'playwright-core';

const sourceRoot = process.argv[2];
if (!sourceRoot) throw Error('Indica la carpeta de recursos original.');
const inspectOnly = process.argv.includes('--inspect');
const verifyOnly = process.argv.includes('--verify');
const work = 'storage/app/revision-3/media';
const output = 'public/media/studio';
await fs.mkdir(work, { recursive: true });
await fs.mkdir(output, { recursive: true });
const sources = [
    { name: 'inicio-charlotte-may', source: '5823855-uhd_3840_2160_24fps.mp4', pexels: 5823855 },
    { name: 'estudio-arthouse', source: '8449347-uhd_3840_2160_25fps.mp4', pexels: 8449347 },
];
for (const item of sources) await fs.copyFile(path.join(sourceRoot, item.source), `${work}/${item.source}`);

// Leer las tablas temporales ISO BMFF permite comprobar FPS y codec sin otra dependencia.
function boxes(buffer, start = 0, end = buffer.length) {
    const result = [];
    for (let position = start; position + 8 <= end;) {
        let length = buffer.readUInt32BE(position);
        let header = 8;
        if (length === 1) { length = Number(buffer.readBigUInt64BE(position + 8)); header = 16; }
        if (length === 0) length = end - position;
        if (length < header || position + length > end) break;
        result.push({ type: buffer.toString('ascii', position + 4, position + 8), start: position + header, end: position + length });
        position += length;
    }
    return result;
}
function trackMetadata(buffer) {
    const moov = boxes(buffer).find(box => box.type === 'moov');
    const tracks = [];
    for (const track of boxes(buffer, moov.start, moov.end).filter(box => box.type === 'trak')) {
        const mdia = boxes(buffer, track.start, track.end).find(box => box.type === 'mdia');
        const children = boxes(buffer, mdia.start, mdia.end);
        const handler = children.find(box => box.type === 'hdlr');
        const type = buffer.toString('ascii', handler.start + 8, handler.start + 12);
        const mdhd = children.find(box => box.type === 'mdhd');
        const timescale = buffer.readUInt32BE(mdhd.start + (buffer[mdhd.start] === 1 ? 20 : 12));
        const minf = children.find(box => box.type === 'minf');
        const stbl = boxes(buffer, minf.start, minf.end).find(box => box.type === 'stbl');
        const tables = boxes(buffer, stbl.start, stbl.end);
        const stsd = tables.find(box => box.type === 'stsd');
        const sample = boxes(buffer, stsd.start + 8, stsd.end)[0];
        const stts = tables.find(box => box.type === 'stts');
        const entries = buffer.readUInt32BE(stts.start + 4);
        let frames = 0;
        let ticks = 0;
        for (let index = 0; index < entries; index++) {
            const count = buffer.readUInt32BE(stts.start + 8 + index * 8);
            frames += count;
            ticks += count * buffer.readUInt32BE(stts.start + 12 + index * 8);
        }
        if (!frames) {
            const tkhd = boxes(buffer, track.start, track.end).find(box => box.type === 'tkhd');
            const trackId = buffer.readUInt32BE(tkhd.start + (buffer[tkhd.start] === 1 ? 20 : 12));
            for (const moof of boxes(buffer).filter(box => box.type === 'moof')) {
                for (const traf of boxes(buffer, moof.start, moof.end).filter(box => box.type === 'traf')) {
                    const fragments = boxes(buffer, traf.start, traf.end);
                    const tfhd = fragments.find(box => box.type === 'tfhd');
                    if (!tfhd || buffer.readUInt32BE(tfhd.start + 4) !== trackId) continue;
                    const flags = buffer.readUInt32BE(tfhd.start) & 0xffffff;
                    let offset = tfhd.start + 8 + (flags & 1 ? 8 : 0) + (flags & 2 ? 4 : 0);
                    const duration = flags & 8 ? buffer.readUInt32BE(offset) : 0;
                    for (const trun of fragments.filter(box => box.type === 'trun')) {
                        const sampleFlags = buffer.readUInt32BE(trun.start) & 0xffffff;
                        const count = buffer.readUInt32BE(trun.start + 4);
                        offset = trun.start + 8 + (sampleFlags & 1 ? 4 : 0) + (sampleFlags & 4 ? 4 : 0);
                        for (let index = 0; index < count; index++) {
                            frames++;
                            ticks += sampleFlags & 0x100 ? buffer.readUInt32BE(offset) : duration;
                            for (const flag of [0x100, 0x200, 0x400, 0x800]) if (sampleFlags & flag) offset += 4;
                        }
                    }
                }
            }
        }
        tracks.push({ type, codec: sample?.type, frames, seconds: ticks / timescale, fps: frames / (ticks / timescale) });
    }
    return tracks;
}

if (verifyOnly) {
    const prepared = JSON.parse(await fs.readFile(`${work}/prepared.json`, 'utf8'));
    for (const item of prepared.videos) {
        item.web.tracks = trackMetadata(await fs.readFile(`${output}/${item.name}.mp4`));
        if (item.web.tracks.length !== 1 || item.web.tracks[0].seconds < 5) throw Error('Duración o pistas de salida no confirmadas.');
        item.web.duration = item.web.tracks[0].seconds;
    }
    await fs.writeFile(`${work}/prepared.json`, JSON.stringify(prepared, null, 2));
    console.log(JSON.stringify(prepared, null, 2));
    process.exit(0);
}

const server = http.createServer(async (request, response) => {
    const item = sources.find(item => request.url === `/${item.source}`);
    if (!item) { response.writeHead(200, { 'Content-Type': 'text/html' }); response.end('<!doctype html><html lang="es"><title>Preparación local</title></html>'); return; }
    const file = `${work}/${item.source}`;
    const size = (await fs.stat(file)).size;
    const range = request.headers.range?.match(/bytes=(\d+)-(\d*)/);
    const start = range ? Number(range[1]) : 0;
    const end = range?.[2] ? Number(range[2]) : size - 1;
    response.writeHead(range ? 206 : 200, { 'Content-Type': 'video/mp4', 'Accept-Ranges': 'bytes', 'Content-Length': end - start + 1, ...(range ? { 'Content-Range': `bytes ${start}-${end}/${size}` } : {}) });
    createReadStream(file, { start, end }).pipe(response);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const manifest = { encoder: 'Chrome MediaRecorder H.264; captura de canvas sin pista de audio', videos: [], portraits: [] };
try {
    const page = await browser.newPage();
    await page.goto(origin);
    for (const item of sources) {
        const buffer = await fs.readFile(`${work}/${item.source}`);
        const frames = await page.evaluate(async ({ url, encode }) => {
            const video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.src = url;
            document.body.append(video);
            await new Promise((resolve, reject) => { video.onloadeddata = resolve; video.onerror = reject; });
            const canvas = document.createElement('canvas');
            canvas.width = 1280;
            canvas.height = Math.round(video.videoHeight / video.videoWidth * canvas.width / 2) * 2;
            const context = canvas.getContext('2d');
            const posters = [];
            for (const fraction of [0.08, 0.5, 0.85]) {
                video.currentTime = video.duration * fraction;
                await new Promise(resolve => video.onseeked = resolve);
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                posters.push(canvas.toDataURL('image/png').split(',')[1]);
            }
            const metadata = { width: video.videoWidth, height: video.videoHeight, duration: video.duration };
            let encoded = null;
            if (encode) {
                video.currentTime = 0;
                await new Promise(resolve => video.onseeked = resolve);
                const stream = canvas.captureStream(24);
                const mimeType = 'video/mp4;codecs=avc1.42001E';
                if (!MediaRecorder.isTypeSupported(mimeType)) throw Error('Codificador H.264 no disponible.');
                const recorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 1100000 });
                const chunks = [];
                recorder.ondataavailable = event => { if (event.data.size) chunks.push(event.data); };
                const complete = new Promise(resolve => recorder.onstop = resolve);
                let drawing = true;
                const draw = () => { if (!drawing) return; context.drawImage(video, 0, 0, canvas.width, canvas.height); video.requestVideoFrameCallback(draw); };
                draw();
                recorder.start();
                await video.play();
                await new Promise(resolve => {
                    video.onended = resolve;
                    setTimeout(resolve, Math.min(video.duration, 14) * 1000);
                });
                video.pause();
                drawing = false;
                recorder.stop();
                await complete;
                stream.getTracks().forEach(track => track.stop());
                const blob = new Blob(chunks, { type: mimeType });
                encoded = await new Promise(resolve => { const reader = new FileReader(); reader.onload = () => resolve(reader.result.split(',')[1]); reader.readAsDataURL(blob); });
            }
            video.remove();
            return { metadata, posters, encoded };
        }, { url: `${origin}/${item.source}`, encode: !inspectOnly });
        const tiles = await Promise.all(frames.posters.map(frame => sharp(Buffer.from(frame, 'base64')).resize(360).png().toBuffer()));
        await sharp({ create: { width: 1080, height: 203, channels: 3, background: '#fff' } }).composite(tiles.map((input, index) => ({ input, left: index * 360, top: 0 }))).png().toFile(`${work}/${item.name}-contacto.png`);
        const record = { ...item, original: { ...frames.metadata, bytes: buffer.length, tracks: trackMetadata(buffer) } };
        if (!inspectOnly) {
            const encoded = Buffer.from(frames.encoded, 'base64');
            await fs.writeFile(`${output}/${item.name}.mp4`, encoded);
            await sharp(Buffer.from(frames.posters[0], 'base64')).webp({ quality: 82 }).toFile(`${output}/${item.name}-poster.webp`);
            record.web = { width: 1280, height: 720, bytes: encoded.length, tracks: trackMetadata(encoded), poster_bytes: (await fs.stat(`${output}/${item.name}-poster.webp`)).size };
        }
        manifest.videos.push(record);
    }
    if (!inspectOnly) {
        for (const portrait of [
            { name: 'angelo-trinidad', source: '023e7c2f-909d-4493-a627-4dc7008cfccd.png' },
            { name: 'nicole-arana', source: 'f16c62f5-da71-453f-ad39-146bff6152e9.png' },
        ]) {
            const original = await fs.readFile(path.join(sourceRoot, portrait.source));
            await fs.copyFile(path.join(sourceRoot, portrait.source), `${work}/${portrait.source}`);
            const metadata = await sharp(original).metadata();
            const result = await sharp(original).resize({ width: 800, height: 1000, fit: 'inside', withoutEnlargement: true }).webp({ quality: 86 }).toFile(`${output}/${portrait.name}.webp`);
            manifest.portraits.push({ ...portrait, original: { width: metadata.width, height: metadata.height, bytes: original.length }, web: { width: result.width, height: result.height, bytes: result.size } });
        }
    }
    await fs.writeFile(`${work}/${inspectOnly ? 'inspection' : 'prepared'}.json`, JSON.stringify(manifest, null, 2));
    console.log(JSON.stringify(manifest, null, 2));
} finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
}
