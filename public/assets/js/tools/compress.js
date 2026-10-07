// PDF Sıkıştır: düzey seçimi; sonuçta gerçek boyut değişimi gösterilir (küçülmediyse açıkça söylenir).
import { t } from '../app.js';
import { setupSource } from './source.js';
import { formatSize, runOperation } from './common.js';

const page = document.querySelector('[data-tool="compress"]');
const form = page.querySelector('[data-settings]');
const runButton = form.querySelector('[data-run]');
const runStatus = form.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');

let source = null;

setupSource(page.querySelector('[data-source]'), (selected) => {
    source = selected;
    runButton.disabled = !selected;
    resultSection.hidden = true;
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!source) {
        return;
    }
    const level = form.querySelector('input[name="level"]:checked').value;
    const result = await runOperation('compress', { document: source.documentId, version: source.version, level },
        { button: runButton, status: runStatus, resultSection });
    if (!result) {
        return;
    }

    const meta = result.meta;
    const line = document.createElement('p');
    line.className = 'mb-3 fw-semibold';
    line.dataset.resultExtra = '';
    line.textContent = result.changed
        ? t('compress.result_sizes', { before: formatSize(meta.size_before), after: formatSize(meta.size_after), percent: meta.saved_percent })
        : t('compress.result_no_gain', { before: formatSize(meta.size_before), after: formatSize(meta.size_after) });
    resultSection.querySelector('[data-result-outputs]').before(line);
});
