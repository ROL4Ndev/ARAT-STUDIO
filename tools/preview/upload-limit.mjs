import http from 'node:http';
import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const result = await new Promise((resolve, reject) => {
    const request = http.request(`${process.env.ARAT_TEST_BASE || 'http://127.0.0.1:8765'}/solicitar-proyecto`, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': 65 * 1024 * 1024 },
    }, response => {
        const chunks = [];
        response.on('data', chunk => chunks.push(chunk));
        response.on('end', () => resolve({ bytes_sent: 65 * 1024 * 1024, status: response.statusCode,
            controlled_message: Buffer.concat(chunks).toString().includes('enlace externo') }));
    });
    request.on('error', reject);
    request.setTimeout(120000, () => request.destroy(Error('Tiempo agotado')));
    request.end(Buffer.alloc(65 * 1024 * 1024, 'a'));
});
console.log(JSON.stringify(result));
if (process.argv.includes('--expect-413')) {
    assert.equal(result.status, 413);
    assert.equal(result.controlled_message, true);
    await writeFile('docs/final/upload-limit.json', JSON.stringify(result, null, 2));
}
