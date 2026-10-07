// OCR: kaynak seç ve çalıştır (yalnızca sunucuda araçlar mevcutsa yüklenir).
import { setupSource } from './source.js';
import { runOperation } from './common.js';

const page = document.querySelector('[data-tool="ocr"]');
const runButton = page.querySelector('[data-run]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');
let source = null;

setupSource(page.querySelector('[data-source]'), (selected) => {
    source = selected;
    runButton.disabled = !selected;
    resultSection.hidden = true;
});

runButton.addEventListener('click', () => {
    runOperation('ocr', { document: source.documentId, version: source.version }, { button: runButton, status: runStatus, resultSection });
});
