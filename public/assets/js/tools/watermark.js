// Filigran Ekle: ayarlar, ilk sayfa üzerinde yaklaşık canlı önizleme, sayfa aralığı doğrulaması.
import { t } from '../app.js';
import { parsePageRanges } from '../page-range.js';
import { ThumbnailSource } from '../pdf-preview.js';
import { setupSource } from './source.js';
import { runOperation } from './common.js';

const page = document.querySelector('[data-tool="watermark"]');
const form = page.querySelector('[data-settings]');
const runButton = form.querySelector('[data-run]');
const runStatus = form.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');
const previewSection = page.querySelector('[data-preview-section]');
const preview = page.querySelector('[data-preview]');
const previewImage = page.querySelector('[data-preview-image]');
const previewText = page.querySelector('[data-preview-text]');
const opacityValue = form.querySelector('[data-opacity-value]');
const textInput = form.querySelector('#wm-text');
const pagesInput = form.querySelector('#wm-pages');
const pagesError = form.querySelector('[data-pages-error]');

let source = null;

function settings() {
    const data = new FormData(form);
    return {
        text: String(data.get('text') ?? '').trim(),
        position: data.get('position'),
        rotation: Number(data.get('rotation')),
        opacity: Number(data.get('opacity')) / 100,
        font_size: Number(data.get('font_size')),
        color: data.get('color'),
        bold: data.get('bold') === 'on',
        layer: data.get('layer'),
    };
}

function validate() {
    const s = settings();
    const textOk = s.text.length >= 1 && s.text.length <= 100;
    textInput.classList.toggle('is-invalid', !textOk && textInput.value !== '');

    let pagesOk = true;
    if (source && pagesInput.value.trim() !== '') {
        const parsed = parsePageRanges(pagesInput.value, source.pages);
        pagesOk = !parsed.error;
        pagesError.textContent = parsed.error ? t(parsed.error, parsed.replace) : '';
    }
    pagesInput.classList.toggle('is-invalid', !pagesOk);
    runButton.disabled = !source || !textOk || !pagesOk;
    return !runButton.disabled;
}

/**
 * Yaklaşık önizleme: CSS ile ilk sayfa küçük resminin üzerine metin.
 * Yazı boyutu önizleme genişliğine göre ölçeklenir (A4 genişliği ~595 pt varsayımı).
 */
function updatePreview() {
    const s = settings();
    opacityValue.textContent = t('watermark.opacity_value', { value: Math.round(s.opacity * 100) });
    previewText.textContent = s.text;
    const scale = (preview.clientWidth || 300) / 595;
    // CSSOM ile stil atama (CSP uyumlu)
    Object.assign(previewText.style, {
        color: s.color,
        opacity: String(s.opacity),
        fontSize: Math.max(6, s.font_size * scale) + 'px',
        fontWeight: s.bold ? '700' : '400',
        transform: `translate(-50%, -50%) rotate(${-s.rotation}deg)`,
    });
    preview.dataset.position = s.position;
    validate();
}

setupSource(page.querySelector('[data-source]'), async (selected) => {
    source = selected;
    previewSection.hidden = !selected;
    resultSection.hidden = true;
    validate();
    if (!selected) {
        return;
    }
    try {
        const thumbs = await new ThumbnailSource({ documentId: selected.documentId, version: selected.version, maxDimension: 400 }).init();
        previewImage.src = await thumbs.url(1);
        previewImage.addEventListener('load', updatePreview, { once: true });
    } catch {
        previewSection.hidden = true;
    }
});

form.addEventListener('input', updatePreview);
form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!validate()) {
        textInput.classList.toggle('is-invalid', settings().text === '');
        return;
    }
    runOperation('watermark', {
        document: source.documentId,
        version: source.version,
        settings: settings(),
        pages: pagesInput.value,
    }, { button: runButton, status: runStatus, resultSection });
});

updatePreview();
