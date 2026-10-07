// Office → PDF: dosyayı yükle (orijinal korunur), ardından dönüştür.
import { api, t } from '../app.js';
import { bindDropzone, renderStatus, uploadFile } from '../upload.js';
import { showResult } from './common.js';

const page = document.querySelector('[data-tool="office"]');
const zone = page.querySelector('[data-dropzone]');
const status = zone.querySelector('[data-dropzone-status]');
const resultSection = page.querySelector('[data-result]');
let busy = false;

bindDropzone(zone, async (files) => {
    if (busy) {
        return;
    }
    busy = true;
    resultSection.hidden = true;
    const file = files[0];

    const upload = await uploadFile(file, {
        office: true,
        onProgress: (p) => renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), p),
    });
    if (!upload.ok) {
        renderStatus(status, 'error', upload.error.message);
        busy = false;
        return;
    }

    renderStatus(status, 'processing', t('office.converting'));
    const result = await api('/operations/office', { method: 'POST', json: { document: upload.document.id } });
    busy = false;
    if (!result.ok) {
        renderStatus(status, 'error', result.error.message);
        return;
    }
    status.replaceChildren();
    showResult(resultSection, result);
});
