// Local OCR or Evren OCR. Credentials are never sent to the browser.
import { setupSource } from './source.js';
import { runOperation } from './common.js';
import { config, t } from '../app.js';
import { openPdf, renderPage } from '../pdf-preview.js';
import { renderStatus } from '../upload.js';

const page = document.querySelector('[data-tool="ocr"]');
const runButton = page.querySelector('[data-run]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');
let source = null;
let busy = false;
const evren = page.dataset.evren === '1';
const consent = page.querySelector('[data-evren-consent]');
const sourceRoot = page.querySelector('[data-source]');

function refresh() {
    runButton.disabled = busy || !source || (evren && (!consent.checked || source.pages > Number(page.dataset.maxPages)));
}
consent?.addEventListener('change', refresh);

setupSource(page.querySelector('[data-source]'), (selected) => {
    source = selected;
    if (consent) consent.checked = false;
    refresh();
    resultSection.hidden = true;
    if (evren && selected?.pages > Number(page.dataset.maxPages)) {
        renderStatus(runStatus, 'error', t('ocr.too_many_pages', { max: page.dataset.maxPages }));
    }
});

runButton.addEventListener('click', async () => {
    if (runButton.disabled || !source) return;
    const selected = { ...source };
    busy = true;
    sourceRoot.inert = true;
    refresh();
    const controls = [...sourceRoot.querySelectorAll('input, button')];
    const disabledStates = controls.map((element) => element.disabled);
    controls.forEach((element) => { element.disabled = true; });
    if (consent) consent.disabled = true;
    let pdf = null;
    try {
        let payload = { document: selected.documentId, version: selected.version };
        if (evren) {
            payload = new FormData();
            payload.append('document', selected.documentId);
            payload.append('version', String(selected.version));
            payload.append('evren_consent', '1');
            pdf = await openPdf(`${config.apiUrl}/documents/${selected.documentId}/versions/${selected.version}/download?inline=1`);
            if (pdf.numPages > Number(page.dataset.maxPages)) throw new Error(t('ocr.too_many_pages', { max: page.dataset.maxPages }));
            let bytes = 0;
            for (let number = 1; number <= pdf.numPages; number++) {
                renderStatus(runStatus, 'processing', t('ocr.preparing', { current: number, total: pdf.numPages }));
                const pdfPage = await pdf.getPage(number);
                const viewport = pdfPage.getViewport({ scale: 200 / 72 });
                const canvas = await renderPage(pdf, number, {
                    maxWidth: Math.min(3000, viewport.width), maxHeight: Math.min(3000, viewport.height), pixelRatio: 1,
                });
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
                canvas.width = 0;
                canvas.height = 0;
                if (!blob || blob.size > Number(page.dataset.imageLimit)) throw new Error(t('ocr.images_too_large'));
                bytes += blob.size + 1024;
                if (bytes + 65536 > Number(page.dataset.postLimit)) throw new Error(t('ocr.images_too_large'));
                payload.append('page_' + number, blob, 'page-' + number + '.jpg');
            }
        }
        await runOperation('ocr', payload, { button: runButton, status: runStatus, resultSection });
    } catch (error) {
        renderStatus(runStatus, 'error', error.message || t('preview.failed'));
    } finally {
        if (pdf) await pdf.destroy().catch(() => {});
        controls.forEach((element, index) => { element.disabled = disabledStates[index]; });
        if (consent) consent.disabled = false;
        sourceRoot.inert = false;
        busy = false;
        refresh();
    }
});
