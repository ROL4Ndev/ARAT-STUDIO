import fs from 'node:fs';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';

const inventory = JSON.parse(fs.readFileSync('docs/revision-2/inventario-trabajo.json', 'utf8'));
let originals = 0;
for (const group of inventory.groups) for (const image of group.images) {
    assert.equal(crypto.createHash('sha256').update(fs.readFileSync(`public/${image.path}`)).digest('hex'), image.sha256, image.path);
    originals++;
}
const candidates = [...new Set(execFileSync('git', ['ls-files', '-m', '-o', '--exclude-standard', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean))];
const excludedUserFile = 'revision-arat.diff';
const files = candidates.filter(file => file !== excludedUserFile);
const forbidden = files.filter(file => /(?:^|\/)(?:\.env(?:\..*)?|state\.json)$|\.(?:sqlite|sqlite3|db)$/i.test(file));
assert.deepEqual(forbidden, []);
const suspicious = [];
for (const file of files.filter(file => /\.(?:php|js|mjs|json|md|txt|css)$/.test(file))) {
    const content = fs.readFileSync(file, 'utf8');
    if (/APP_KEY\s*=\s*base64:[A-Za-z0-9+/]{20,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|https?:\/\/[^\s'"<>]+[?&](?:token|signature)=[A-Za-z0-9]{30,}|\/acceso-expediente\/[a-f0-9]{64}/.test(content)) suspicious.push(file);
}
assert.deepEqual(suspicious, []);
const report = {
    originals_verified_sha256: originals,
    changed_or_new_versionable_files_checked: files.length,
    forbidden_artifacts: forbidden,
    credential_or_access_url_patterns: suspicious,
    existing_user_diff_excluded_and_preserved: excludedUserFile,
    test_case_guard_changed: execFileSync('git', ['diff', '--', 'tests/TestCase.php'], { encoding: 'utf8' }).length !== 0,
    limitation: 'Inspección por nombres y patrones, complementada con revisión de los archivos modificados; no es un detector universal de secretos.',
};
assert.equal(report.test_case_guard_changed, false);
fs.writeFileSync('docs/revision-2/artifact-checks.json', JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
