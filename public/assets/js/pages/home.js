// Ana sayfa: tek PDF yükle → belge sayfasına git. Seçilen dosya ad/boyut/ilerleme ile önizlenir.
import { t } from '../app.js';
import { bindDropzone, renderFileChips, renderStatus, uploadFile } from '../upload.js';

const zone = document.querySelector('[data-dropzone]');
const status = zone?.querySelector('[data-dropzone-status]');
const list = zone?.querySelector('[data-dropzone-files]');
let busy = false;

if (zone && status) {
    bindDropzone(zone, async (files) => {
        if (busy) {
            return;
        }
        busy = true;
        const file = files[0];
        const [chip] = list ? renderFileChips(list, [file]) : [null];
        renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), chip ? null : 0);

        const result = await uploadFile(file, {
            onProgress: (percent) => {
                if (chip) {
                    chip.progress.value = percent;
                } else {
                    renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), percent);
                }
            },
        });

        busy = false;
        if (result.ok) {
            if (chip) {
                chip.progress.value = 100;
            }
            renderStatus(status, 'success', result.message);
            window.location.href = result.document.url;
        } else {
            chip?.progress.remove();
            renderStatus(status, 'error', result.error.message);
        }
    });
}
