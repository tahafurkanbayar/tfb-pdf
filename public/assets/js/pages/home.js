// Ana sayfa: tek PDF yükle → belge sayfasına git.
import { t } from '../app.js';
import { bindDropzone, renderStatus, uploadFile } from '../upload.js';

const zone = document.querySelector('[data-dropzone]');
const status = zone?.querySelector('[data-dropzone-status]');
let busy = false;

if (zone && status) {
    bindDropzone(zone, async (files) => {
        if (busy) {
            return;
        }
        busy = true;
        const file = files[0];
        renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), 0);

        const result = await uploadFile(file, {
            onProgress: (percent) => renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), percent),
        });

        busy = false;
        if (result.ok) {
            renderStatus(status, 'success', result.message);
            window.location.href = result.document.url;
        } else {
            renderStatus(status, 'error', result.error.message);
        }
    });
}
