import fs from 'node:fs';
import zlib from 'node:zlib';
import assert from 'node:assert/strict';

const xml = fs.readFileSync('storage/app/revision-2-tests.xml', 'utf8');
const suite = xml.match(/<testsuite\b[^>]*>/)?.[0];
const attribute = name => Number(suite.match(new RegExp(`${name}="([^"]+)"`))?.[1] ?? 0);
const summary = Object.fromEntries(['tests', 'assertions', 'failures', 'errors', 'skipped', 'time'].map(name => [name, attribute(name)]));
assert.equal(summary.failures + summary.errors, 0);
assert.ok(summary.tests > 0 && summary.assertions > 0);
fs.writeFileSync('docs/revision-2/tests-summary.json', JSON.stringify({ php: 'Herd 8.5.12', database: 'SQLite :memory:', ...summary }, null, 2));

const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json', 'utf8'));
const assets = {};
for (const [entry, chunk] of Object.entries(manifest)) {
    if (!chunk.isEntry && !chunk.isDynamicEntry) continue;
    const bytes = fs.readFileSync(`public/build/${chunk.file}`);
    assets[entry] = { bytes: bytes.length, gzip_bytes: zlib.gzipSync(bytes).length, dynamic: Boolean(chunk.isDynamicEntry) };
}
fs.writeFileSync('docs/revision-2/build-summary.json', JSON.stringify({ vite: '7.3.7', node: process.version, assets }, null, 2));
const before = JSON.parse(fs.readFileSync('docs/revision-2/inventario-trabajo.json', 'utf8'));
const after = JSON.parse(fs.readFileSync('docs/revision-2/inventario-trabajo-final.json', 'utf8'));
assert.deepEqual(after.groups, before.groups);
console.log(JSON.stringify({ tests: summary, inventory_comparison: 'sin cambios en archivos y coincidencias de la base existente', assets }, null, 2));
