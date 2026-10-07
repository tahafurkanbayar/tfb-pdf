// Sayfa kartları: sıralama, döndürme, karartma gibi sayfa bazlı araçlar için ortak ızgara.
import { icon, t } from '../app.js';
import { ThumbnailSource, lazyThumbnails } from '../pdf-preview.js';

export function iconButton(name, label, className = 'btn btn-sm btn-light border') {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.setAttribute('aria-label', label);
    button.title = label;
    button.append(icon(name));
    return button;
}

/**
 * Izgarayı kurar; her sayfa için kart oluşturur. buildControls(card, page) kontrol butonlarını ekler.
 * @returns {Promise<ThumbnailSource|null>}
 */
export async function renderPageCards(grid, source, buildControls) {
    grid.replaceChildren();
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
        const card = document.createElement('div');
        card.className = 'page-card';
        card.dataset.page = String(page);
        card.setAttribute('role', 'listitem');

        const handle = document.createElement('div');
        handle.className = 'page-card-handle thumb is-loading';
        const img = document.createElement('img');
        img.alt = '';
        img.dataset.page = String(page);
        const label = document.createElement('span');
        label.className = 'thumb-label';
        label.textContent = String(page);
        handle.append(img, label);

        const controls = document.createElement('div');
        controls.className = 'page-card-controls';
        card.append(handle, controls);
        buildControls(card, page, controls);
        grid.append(card);
    }
    grid.setAttribute('role', 'list');
    lazyThumbnails(grid, thumbnails);
    return thumbnails;
}
