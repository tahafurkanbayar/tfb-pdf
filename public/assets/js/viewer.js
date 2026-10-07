// Büyük sayfa görüntüleyici (Bootstrap modal + PDF.js). Klavye: ←/→ sayfa değiştirir.
import { t } from './app.js';
import { renderPage } from './pdf-preview.js';

export class PageViewer {
    constructor(modalElement) {
        this.element = modalElement;
        this.modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        this.canvas = modalElement.querySelector('[data-viewer-canvas]');
        this.status = modalElement.querySelector('[data-viewer-status]');
        this.indicator = modalElement.querySelector('[data-viewer-indicator]');
        this.prev = modalElement.querySelector('[data-viewer-prev]');
        this.next = modalElement.querySelector('[data-viewer-next]');
        this.source = null;
        this.page = 1;
        this.rotationFor = () => 0;
        this.renderToken = 0;

        this.prev.addEventListener('click', () => this.show(this.page - 1));
        this.next.addEventListener('click', () => this.show(this.page + 1));
        modalElement.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                this.show(this.page - 1);
            } else if (event.key === 'ArrowRight') {
                this.show(this.page + 1);
            }
        });
    }

    /**
     * source: ThumbnailSource (pdf() ve pageCount sağlar). rotationFor(page): ek önizleme döndürmesi.
     */
    open(source, page, rotationFor = () => 0) {
        this.source = source;
        this.rotationFor = rotationFor;
        this.modal.show();
        this.show(page);
    }

    async show(page) {
        if (!this.source || page < 1 || page > this.source.pageCount) {
            return;
        }
        this.page = page;
        const token = ++this.renderToken;
        this.indicator.textContent = t('preview.page_of', { current: page, total: this.source.pageCount });
        this.prev.disabled = page <= 1;
        this.next.disabled = page >= this.source.pageCount;
        this.status.textContent = t('preview.loading');
        this.status.hidden = false;
        this.canvas.hidden = true;

        try {
            const pdf = await this.source.pdf();
            const body = this.element.querySelector('.viewer-body');
            const width = Math.max(320, (body?.clientWidth || 900) - 32);
            const offscreen = await renderPage(pdf, page, { maxWidth: width, maxHeight: window.innerHeight * 0.75, extraRotation: this.rotationFor(page) });
            if (token !== this.renderToken) {
                return; // Bu arada başka sayfaya geçildi
            }
            this.canvas.width = offscreen.width;
            this.canvas.height = offscreen.height;
            this.canvas.getContext('2d').drawImage(offscreen, 0, 0);
            this.canvas.setAttribute('aria-label', t('preview.page_label', { number: page }));
            this.canvas.setAttribute('role', 'img');
            this.status.hidden = true;
            this.canvas.hidden = false;
        } catch {
            this.status.textContent = t('preview.failed');
        }
    }
}
