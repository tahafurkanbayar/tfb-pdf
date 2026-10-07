// PDF.js tabanlı önizleme: belge açma, sayfa çizimi, küçük resim üretimi ve sunucu önbelleği.
import * as pdfjsLib from '../vendor/pdfjs/pdf.min.mjs';
import { config } from './app.js';

const vendor = new URL('../vendor/pdfjs/', import.meta.url).href;
pdfjsLib.GlobalWorkerOptions.workerSrc = vendor + 'pdf.worker.min.mjs';

/**
 * PDF'i açar. Range istekleri sayesinde büyük dosyalarda yalnızca gereken bölümler indirilir.
 */
export function openPdf(url) {
    return pdfjsLib.getDocument({
        url,
        withCredentials: true,
        cMapUrl: vendor + 'cmaps/',
        cMapPacked: true,
        standardFontDataUrl: vendor + 'standard_fonts/',
        wasmUrl: vendor + 'wasm/',
        iccUrl: vendor + 'iccs/',
        enableXfa: false,
        disableAutoFetch: true,
        rangeChunkSize: 131072,
    }).promise;
}

/**
 * Sayfayı tuvale çizer. extraRotation: kullanıcı önizlemesinde ek döndürme (0/90/180/270).
 */
export async function renderPage(pdf, pageNumber, { maxWidth = 800, maxHeight = 1100, extraRotation = 0, canvas = null } = {}) {
    const page = await pdf.getPage(pageNumber);
    const rotation = (page.rotate + extraRotation) % 360;
    const base = page.getViewport({ scale: 1, rotation });
    const scale = Math.min(maxWidth / base.width, maxHeight / base.height);
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const viewport = page.getViewport({ scale: scale * ratio, rotation });

    const target = canvas ?? document.createElement('canvas');
    target.width = Math.floor(viewport.width);
    target.height = Math.floor(viewport.height);
    await page.render({ canvas: target, viewport, background: '#ffffff' }).promise;
    page.cleanup();

    return target;
}

/**
 * Saklanan bir PDF sürümünün sayfa küçük resimleri.
 * Önce sunucu önbelleğine bakar; yoksa tarayıcıda çizer ve önbelleğe gönderir.
 */
export class ThumbnailSource {
    constructor({ documentId, version, maxDimension = 220 }) {
        this.documentId = documentId;
        this.version = version;
        this.maxDimension = maxDimension;
        this.base = `${config.apiUrl}/documents/${documentId}/versions/${version}`;
        this.cached = new Set();
        this.cacheEnabled = false;
        this.pageCount = 0;
        this.urls = new Map();
        this.queue = Promise.resolve();
        this.pdfPromise = null;
    }

    async init() {
        const response = await fetch(this.base + '/previews', { headers: { Accept: 'application/json', 'X-Locale': config.locale }, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error?.message ?? 'preview');
        }
        this.pageCount = data.pages ?? 0;
        this.cacheEnabled = data.cache_enabled === true;
        this.maxDimension = Math.min(this.maxDimension, data.max_dimension ?? this.maxDimension);
        data.cached.forEach((page) => this.cached.add(page));
        return this;
    }

    pdf() {
        this.pdfPromise ??= openPdf(this.base + '/download?inline=1');
        return this.pdfPromise;
    }

    /**
     * Sayfa için görsel URL'si (sunucu önbelleği veya blob:).
     */
    url(page) {
        if (this.cached.has(page)) {
            return Promise.resolve(`${this.base}/previews/${page}`);
        }
        if (!this.urls.has(page)) {
            // Aynı anda tek çizim: düşük kaynaklı cihazlarda bellek ve CPU'yu korur
            const job = this.queue.then(() => this.render(page));
            this.queue = job.catch(() => {});
            this.urls.set(page, job);
        }
        return this.urls.get(page);
    }

    async render(page) {
        const pdf = await this.pdf();
        const canvas = await renderPage(pdf, page, { maxWidth: this.maxDimension, maxHeight: this.maxDimension });
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
        canvas.width = 0;
        canvas.height = 0;
        if (this.cacheEnabled && blob) {
            // Önbelleğe gönderim başarısız olsa da önizleme çalışmaya devam eder
            fetch(`${this.base}/previews/${page}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'image/jpeg', 'X-CSRF-Token': config.csrfToken },
                credentials: 'same-origin',
                body: blob,
            }).then((r) => r.ok && this.cached.add(page)).catch(() => {});
        }
        return URL.createObjectURL(blob);
    }
}

/**
 * Kap içindeki img[data-page] öğelerini görünür olduklarında doldurur.
 */
export function lazyThumbnails(container, source, { onError = () => {} } = {}) {
    const load = (img) => {
        const page = Number(img.dataset.page);
        source.url(page).then((url) => {
            img.src = url;
            img.closest('.thumb')?.classList.remove('is-loading');
        }).catch(onError);
    };

    if (!('IntersectionObserver' in window)) {
        container.querySelectorAll('img[data-page]').forEach(load);
        return null;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                load(entry.target);
            }
        });
    }, { rootMargin: '300px' });
    container.querySelectorAll('img[data-page]').forEach((img) => observer.observe(img));
    return observer;
}
