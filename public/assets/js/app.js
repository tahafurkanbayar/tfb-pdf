// tfb-pdf — ortak tarayıcı modülü. Build adımı yok; doğrudan ES module olarak yüklenir.

const configElement = document.getElementById('tfb-config');
export const config = configElement ? JSON.parse(configElement.textContent) : { i18n: {}, locale: 'tr' };

/**
 * Çeviri: sunucunun sayfaya eklediği anahtarlar (js.*, states.*, errors.*).
 * Kullanım: t('states.processing'), t('upload.file_too_large', { max: '25 MB' })
 */
export function t(key, replace = {}) {
    let text = config.i18n[key] ?? key;
    for (const [name, value] of Object.entries(replace)) {
        text = text.split(':' + name).join(String(value));
    }
    return text;
}

/**
 * Çoğul seçimi: "tekil|çoğul" biçimindeki çeviriler için (Türkçede tek biçim).
 */
export function tc(key, count, replace = {}) {
    const text = t(key, { count, ...replace });
    if (!text.includes('|')) {
        return text;
    }
    const [one, other] = text.split('|');
    return count === 1 ? one : other;
}

/**
 * SVG ikon (sprite). Dekoratiftir, ekran okuyuculardan gizlenir.
 */
export function icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'bi');
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', config.icons + '#i-' + name);
    svg.append(use);
    return svg;
}

/**
 * JSON API isteği. CSRF token ve arayüz dili otomatik eklenir.
 * Hata durumunda { ok: false, error: { message, category, recoverable, request_id } } döner,
 * istisna fırlatmaz.
 */
export async function api(path, { method = 'GET', body = null, json = null, signal = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Locale': config.locale };
    if (method !== 'GET') {
        headers['X-CSRF-Token'] = config.csrfToken;
    }
    let payload = body;
    if (json !== null) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(json);
    }

    try {
        const response = await fetch(config.apiUrl + path, { method, headers, body: payload, signal, credentials: 'same-origin' });
        const data = await response.json().catch(() => null);
        if (data && typeof data === 'object') {
            return data;
        }
        return { ok: false, error: { message: t('errors.generic'), recoverable: true } };
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }
        return { ok: false, error: { message: t('errors.network'), recoverable: true } };
    }
}

/**
 * Bildirim (toast). type: success | danger | warning | info
 */
export function toast(message, type = 'info') {
    const area = document.getElementById('toast-area');
    if (!area || !window.bootstrap) {
        return;
    }
    const element = document.createElement('div');
    element.className = `toast align-items-center text-bg-${type} border-0`;
    element.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex';
    const text = document.createElement('div');
    text.className = 'toast-body';
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', t('js.dismiss'));
    wrapper.append(text, close);
    element.append(wrapper);
    area.append(element);
    element.addEventListener('hidden.bs.toast', () => element.remove());
    new window.bootstrap.Toast(element, { delay: 6000 }).show();
}

// data-copy="metin" özniteliğine sahip butonlar panoya kopyalar
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }
    try {
        await navigator.clipboard.writeText(button.getAttribute('data-copy'));
        toast(t('js.copied'), 'success');
    } catch {
        toast(t('js.copy_failed'), 'warning');
    }
});
