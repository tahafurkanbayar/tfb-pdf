// Tek belgeli araçlar için kaynak seçimi (ön seçili belge veya yükleme) ve sayfa küçük resmi ızgarası.
import { t, tc } from '../app.js';
import { bindDropzone, renderStatus, uploadFile } from '../upload.js';
import { ThumbnailSource, lazyThumbnails } from '../pdf-preview.js';

/**
 * @param {HTMLElement} root  [data-source] öğesi
 * @param {(source: {documentId, version, pages, name}|null) => void} onChange
 */
export function setupSource(root, onChange) {
    const uploadArea = root.querySelector('[data-source-upload]');
    const selected = root.querySelector('[data-source-selected]');
    const zone = root.querySelector('[data-dropzone]');
    const status = zone.querySelector('[data-dropzone-status]');

    const select = (source) => {
        uploadArea.hidden = true;
        selected.hidden = false;
        selected.querySelector('[data-source-name]').textContent = source.name;
        selected.querySelector('[data-source-meta]').textContent = source.pages ? tc('common.page_count', source.pages) : '';
        onChange(source);
    };

    root.querySelector('[data-source-change]').addEventListener('click', () => {
        selected.hidden = true;
        uploadArea.hidden = false;
        status.replaceChildren();
        onChange(null);
        zone.querySelector('[data-dropzone-input]').focus();
    });

    let busy = false;
    bindDropzone(zone, async (files) => {
        if (busy) {
            return;
        }
        busy = true;
        const file = files[0];
        renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), 0);
        const result = await uploadFile(file, {
            onProgress: (p) => renderStatus(status, 'uploading', t('upload.uploading_named', { name: file.name }), p),
        });
        busy = false;
        if (!result.ok) {
            renderStatus(status, 'error', result.error.message);
            return;
        }
        const original = result.document.versions[0];
        select({ documentId: result.document.id, version: original.number, pages: original.pages, name: result.document.name });
    });

    const preselected = root.dataset.preselected ? JSON.parse(root.dataset.preselected) : null;
    if (preselected) {
        select({ documentId: preselected.id, version: preselected.version, pages: preselected.pages, name: preselected.name });
    }
}

/**
 * Sayfa ızgarası. decorate(button, page) her sayfa öğesini özelleştirir (seçim, döndürme ...).
 * @returns {Promise<ThumbnailSource|null>}
 */
export async function renderPageGrid(grid, source, decorate = () => {}) {
    grid.replaceChildren();
    if (!source) {
        return null;
    }

    const loading = document.createElement('p');
    loading.className = 'small text-body-secondary';
    loading.textContent = t('preview.loading');
    grid.append(loading);

    let thumbnails;
    try {
        thumbnails = await new ThumbnailSource({ documentId: source.documentId, version: source.version }).init();
    } catch {
        loading.textContent = t('preview.failed');
        return null;
    }

    grid.replaceChildren();
    for (let page = 1; page <= thumbnails.pageCount; page++) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'thumb is-loading';
        item.dataset.pageNumber = String(page);
        const img = document.createElement('img');
        img.alt = '';
        img.dataset.page = String(page);
        const label = document.createElement('span');
        label.className = 'thumb-label';
        label.textContent = String(page);
        item.append(img, label);
        decorate(item, page);
        grid.append(item);
    }
    lazyThumbnails(grid, thumbnails);
    return thumbnails;
}
