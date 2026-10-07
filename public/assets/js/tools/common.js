// Araç sayfaları için ortak yardımcılar: işlem çalıştırma, durum ve sonuç gösterimi.
import { api, config, t, tc } from '../app.js';
import { renderStatus } from '../upload.js';

export function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let i = 0;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    // Sunucudaki Size::format ile aynı: tek ondalık, binlik ayırıcı yok, ondalık ayırıcı sayfa dilinden (TR "13,5 KB")
    const number = i === 0
        ? String(size)
        : new Intl.NumberFormat(config.locale, { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: false }).format(size);
    return number + ' ' + units[i];
}

/**
 * /api/operations/{type} çağrısı: buton kilitlenir, "PDF işleniyor..." durumu gösterilir,
 * sonuç veya hata ilgili alana yazılır.
 */
export async function runOperation(type, payload, { button, status, resultSection }) {
    button.disabled = true;
    resultSection.hidden = true;
    renderStatus(status, 'processing', t('states.processing'));

    // FormData (dosya içeren işlemler) veya JSON
    const result = payload instanceof FormData
        ? await api('/operations/' + type, { method: 'POST', body: payload })
        : await api('/operations/' + type, { method: 'POST', json: payload });
    button.disabled = false;

    if (!result.ok) {
        renderStatus(status, 'error', result.error.message + (result.error.recoverable ? '' : ' ' + t('states.fatal')));
        return null;
    }

    status.replaceChildren();
    showResult(resultSection, result);
    return result;
}

export function showResult(section, result) {
    section.hidden = false;
    section.querySelectorAll('[data-result-extra]').forEach((el) => el.remove());
    section.classList.toggle('border-success-subtle', result.changed);
    section.classList.toggle('border-warning-subtle', !result.changed);
    section.querySelector('[data-result-message]').textContent = result.message;

    const outputs = section.querySelector('[data-result-outputs]');
    outputs.replaceChildren();
    for (const output of result.outputs) {
        const li = document.createElement('li');
        li.className = 'd-flex flex-wrap gap-2 align-items-center py-1 small';
        const link = document.createElement('a');
        link.href = output.download_url;
        link.textContent = 'v' + String(output.version).padStart(3, '0') + '.pdf' + (output.label ? ' (' + output.label + ')' : '');
        const meta = document.createElement('span');
        meta.className = 'text-body-secondary';
        meta.textContent = [output.pages ? tc('common.page_count', output.pages) : null, formatSize(output.size)].filter(Boolean).join(' · ');
        const hash = document.createElement('code');
        hash.className = 'hash small';
        hash.title = output.sha256;
        hash.textContent = 'SHA-256 ' + output.sha256.slice(0, 16) + '…';
        li.append(link, meta, hash);
        outputs.append(li);
    }

    const download = section.querySelector('[data-result-download]');
    download.hidden = result.outputs.length !== 1;
    if (result.outputs.length === 1) {
        download.href = result.outputs[0].download_url;
    }
    const zip = section.querySelector('[data-result-zip]');
    zip.hidden = !result.zip_url;
    if (result.zip_url) {
        zip.href = result.zip_url;
    }

    const open = section.querySelector('[data-result-document]');
    open.hidden = !result.document;
    if (result.document) {
        open.href = result.document.url;
    }

    const warnings = section.querySelector('[data-result-warnings]');
    const list = warnings.querySelector('ul');
    list.replaceChildren(...result.warnings.map((text) => {
        const li = document.createElement('li');
        li.textContent = text;
        return li;
    }));
    warnings.hidden = result.warnings.length === 0;

    section.focus();
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

export function announce(element, message) {
    if (element) {
        element.textContent = '';
        window.setTimeout(() => {
            element.textContent = message;
        }, 50);
    }
}
