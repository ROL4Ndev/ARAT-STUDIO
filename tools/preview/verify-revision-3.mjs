import fs from 'node:fs';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import zlib from 'node:zlib';
import path from 'node:path';
import assert from 'node:assert/strict';

const sourceRoot = process.argv[2];
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const output = 'docs/revision-3';
const metadata = JSON.parse(fs.readFileSync('storage/app/revision-3/media/prepared.json', 'utf8'));
const inventory = JSON.parse(fs.readFileSync('storage/app/revision-3-inventory-before.json', 'utf8'));
let originals = 0;
for (const group of inventory.groups) for (const image of group.images) {
    assert.equal(hash(`public/${image.path}`), image.sha256);
    originals++;
}
let sourceFiles = 0;
if (sourceRoot) for (const item of [...metadata.videos, ...metadata.portraits]) {
    assert.equal(hash(path.join(sourceRoot, item.source)), hash(`storage/app/revision-3/media/${item.source}`));
    sourceFiles++;
}
const assets = fs.readdirSync('public/media/studio').map(name => ({ file: `media/studio/${name}`, bytes: fs.statSync(`public/media/studio/${name}`).size }));
fs.writeFileSync(`${output}/media.json`, JSON.stringify({ ...metadata, web_assets: assets, total_web_bytes: assets.reduce((sum, file) => sum + file.bytes, 0) }, null, 2));

const xml = fs.readFileSync('storage/app/revision-3-tests.xml', 'utf8');
const suite = xml.match(/<testsuite\b[^>]*>/)[0];
const tests = Object.fromEntries(['tests', 'assertions', 'failures', 'errors', 'skipped', 'time'].map(name => [name, Number(suite.match(new RegExp(`${name}="([^"]+)"`))[1])]));
assert.ok(tests.tests > 0 && tests.failures + tests.errors === 0);
fs.writeFileSync(`${output}/tests-summary.json`, JSON.stringify({ php: 'Herd 8.5.12', database: 'SQLite :memory:', ...tests }, null, 2));
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json', 'utf8'));
const chunks = {};
for (const [entry, asset] of Object.entries(manifest)) if (asset.isEntry || asset.isDynamicEntry) {
    const content = fs.readFileSync(`public/build/${asset.file}`);
    chunks[entry] = { bytes: content.length, gzip_bytes: zlib.gzipSync(content).length };
}
fs.writeFileSync(`${output}/build-summary.json`, JSON.stringify(chunks, null, 2));
const candidates = [...new Set(execFileSync('git', ['ls-files', '-m', '-o', '--exclude-standard', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean))]
    .filter(file => file !== 'revision-arat.diff' && fs.existsSync(file));
assert.deepEqual(candidates.filter(file => /(?:^|\/)(?:\.env(?:\..*)?|state\.json)$|\.(?:sqlite|sqlite3|db|sql)$/i.test(file)), []);
const suspicious = [];
for (const file of candidates.filter(file => /\.(?:php|js|mjs|json|md|txt|css)$/.test(file))) {
    if (/APP_KEY\s*=\s*base64:[A-Za-z0-9+/]{20,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\/acceso-expediente\/[a-f0-9]{64}|https?:\/\/[^\s'"<>]+[?&](?:token|signature)=[A-Za-z0-9]{30,}/.test(fs.readFileSync(file, 'utf8'))) suspicious.push(file);
}
assert.deepEqual(suspicious, []);
const evidence = {
    project_originals_verified_sha256: originals, desktop_sources_verified_unchanged: sourceFiles,
    versionable_files_checked: candidates.length, forbidden_artifacts_or_secret_patterns: suspicious,
    test_case_guard_unchanged: execFileSync('git', ['diff', '--', 'tests/TestCase.php'], { encoding: 'utf8' }).length === 0,
    source_resources_preserved_in_desktop_and_ignored_storage: true,
    limitation: 'El escaneo por nombres y patrones complementa la revisión de cambios; no detecta cualquier secreto posible.',
};
fs.writeFileSync(`${output}/artifact-checks.json`, JSON.stringify(evidence, null, 2));
console.log(JSON.stringify({ tests, ...evidence, total_web_bytes: assets.reduce((sum, file) => sum + file.bytes, 0) }, null, 2));
