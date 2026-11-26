import { chromium } from 'playwright-core';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const files = [
    ['entrega-version-2', '16-pdf-abierto'],
    ['escenario-pendiente-v1', '19-pdf-pendiente'],
    ['escenario-revision-v1', '20-pdf-revision-v1'],
    ['escenario-revision-v2', '21-pdf-revision-v2'],
    ['escenario-conforme-v1', '22-pdf-conforme'],
];
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const checks = [];
try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 1000 } });
    for (const [name, capture] of files) {
        const file = `docs/tercera-presentacion/archivos/${name}.pdf`;
        const bytes = await readFile(file);
        assert.equal(bytes.subarray(0, 5).toString(), '%PDF-');
        assert.ok(bytes.subarray(-1024).toString().includes('%%EOF'));
        await page.goto(pathToFileURL(resolve(file)).href, { waitUntil: 'load' });
        // El visor PDF nativo se dibuja fuera del DOM de la aplicación.
        await page.waitForTimeout(1300);
        await page.screenshot({ path: `docs/tercera-presentacion/capturas/${capture}.png` });
        checks.push({ file: `${name}.pdf`, bytes: bytes.length, loadedInChrome: true, capture: `${capture}.png` });
    }
} finally {
    await writeFile('docs/tercera-presentacion/pdfs.json', JSON.stringify({ checks }, null, 2));
    console.log(JSON.stringify({ nativePdfViews: checks.length }));
    await browser.close();
}
