// Belge detay sayfası: silme (onaylı, DELETE + CSRF) ve saklama süresi.
import { api, t, toast } from '../app.js';

const root = document.querySelector('[data-document]');
const documentId = root?.getAttribute('data-document');

const deleteButton = document.querySelector('[data-delete-confirm]');
deleteButton?.addEventListener('click', async () => {
    deleteButton.disabled = true;
    deleteButton.textContent = t('js.deleting');
    const result = await api('/documents/' + documentId, { method: 'DELETE' });
    if (result.ok) {
        window.location.href = result.redirect;
        return;
    }
    deleteButton.disabled = false;
    deleteButton.textContent = t('js.delete_retry');
    toast(result.error.message, 'danger');
});

const expiryForm = document.querySelector('[data-expiry-form]');
expiryForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = expiryForm.querySelector('button[type="submit"]');
    submit.disabled = true;
    const policy = expiryForm.querySelector('select').value;
    const result = await api('/documents/' + documentId + '/expiry', { method: 'PUT', json: { policy } });
    submit.disabled = false;
    if (result.ok) {
        toast(result.message, 'success');
        const status = document.querySelector('[data-expiry-status]');
        if (status) {
            status.textContent = result.expiry.expires_at
                ? t('js.expires_on', { date: new Date(result.expiry.expires_at).toLocaleString(document.documentElement.lang) })
                : t('js.expires_never');
        }
    } else {
        toast(result.error.message, 'danger');
    }
});

// Önizleme: seçili sürümün sayfa küçük resimleri (tembel yükleme + sunucu önbelleği) ve büyük görüntüleyici
const previewSection = document.querySelector('[data-preview]');
if (previewSection) {
    const [{ ThumbnailSource, lazyThumbnails }, { PageViewer }] = await Promise.all([
        import('../pdf-preview.js'),
        import('../viewer.js'),
    ]);
    const grid = previewSection.querySelector('[data-preview-grid]');
    const select = previewSection.querySelector('[data-preview-version]');
    const viewerElement = document.getElementById('page-viewer');
    const viewer = viewerElement ? new PageViewer(viewerElement) : null;
    let observer = null;

    const showMessage = (key) => {
        const p = document.createElement('p');
        p.className = 'small text-body-secondary mb-0';
        p.textContent = t(key);
        grid.replaceChildren(p);
    };

    const load = async (version) => {
        observer?.disconnect();
        showMessage('preview.loading');
        let source;
        try {
            source = await new ThumbnailSource({ documentId, version }).init();
        } catch {
            showMessage('preview.failed');
            return;
        }

        grid.replaceChildren();
        for (let page = 1; page <= source.pageCount; page++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'thumb is-loading';
            button.setAttribute('aria-label', t('preview.open_page', { number: page }));
            const img = document.createElement('img');
            img.alt = '';
            img.dataset.page = String(page);
            img.loading = 'lazy';
            const label = document.createElement('span');
            label.className = 'thumb-label';
            label.textContent = String(page);
            button.append(img, label);
            button.addEventListener('click', () => viewer?.open(source, page));
            grid.append(button);
        }
        observer = lazyThumbnails(grid, source, { onError: () => showMessage('preview.failed') });
    };

    select.addEventListener('change', () => load(Number(select.value)));
    load(Number(select.value));
}

// Bütünlük: sunucuda yeniden hesaplama ve tarayıcıda yerel dosya karşılaştırma (dosya gönderilmez)
const integrity = document.querySelector('[data-integrity]');
if (integrity) {
    const verifyButton = integrity.querySelector('[data-verify]');
    const verifyResult = integrity.querySelector('[data-verify-result]');
    verifyButton.addEventListener('click', async () => {
        verifyButton.disabled = true;
        verifyResult.textContent = t('hash.verifying');
        const result = await api('/documents/' + documentId + '/verify');
        verifyButton.disabled = false;
        if (!result.ok) {
            verifyResult.textContent = result.error.message;
            return;
        }
        verifyResult.replaceChildren();
        const summary = document.createElement('p');
        summary.className = result.intact ? 'text-success mb-1' : 'text-danger mb-1';
        summary.textContent = result.message;
        verifyResult.append(summary);
        if (!result.intact) {
            const list = document.createElement('ul');
            list.className = 'mb-0';
            for (const row of result.results.filter((r) => r.status !== 'ok')) {
                const li = document.createElement('li');
                li.textContent = 'v' + String(row.version).padStart(3, '0') + ': ' + t('hash.status.' + row.status);
                list.append(li);
            }
            verifyResult.append(list);
        }
    });

    const hashes = JSON.parse(integrity.dataset.hashes || '[]');
    const compareInput = integrity.querySelector('[data-compare]');
    const compareResult = integrity.querySelector('[data-compare-result]');
    compareInput.addEventListener('change', async () => {
        const file = compareInput.files?.[0];
        if (!file) {
            return;
        }
        if (!window.crypto?.subtle) {
            compareResult.textContent = t('hash.compare_unsupported');
            return;
        }
        compareResult.textContent = t('hash.computing');
        const digest = await window.crypto.subtle.digest('SHA-256', await file.arrayBuffer());
        const hex = [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
        const match = hashes.find((h) => h.sha256 === hex);
        compareResult.className = 'small mt-2 ' + (match ? 'text-success' : 'text-body-secondary');
        compareResult.textContent = match ? t('hash.compare_match', { version: match.label }) : t('hash.compare_no_match', { hash: hex });
    });
}

// İmza talepleri: iptal, yeni bağlantı, imzalı PDF'i yeniden oluşturma
document.querySelectorAll('[data-request]').forEach((box) => {
    const requestId = box.dataset.request;
    const output = box.querySelector('[data-link-output]');

    box.querySelector('[data-cancel-request]')?.addEventListener('click', async () => {
        if (!window.confirm(t('signature.cancel_confirm'))) {
            return;
        }
        const result = await api('/signatures/' + requestId + '/cancel', { method: 'POST', json: {} });
        if (result.ok) {
            window.location.reload();
        } else {
            toast(result.error.message, 'danger');
        }
    });

    box.querySelector('[data-finalize]')?.addEventListener('click', async () => {
        const result = await api('/signatures/' + requestId + '/finalize', { method: 'POST', json: {} });
        toast(result.ok ? result.message : result.error.message, result.ok && result.completed ? 'success' : 'warning');
        if (result.ok && result.completed) {
            window.location.reload();
        }
    });

    box.querySelectorAll('[data-new-link]').forEach((button) => button.addEventListener('click', async () => {
        const result = await api('/signatures/' + requestId + '/signers/' + button.dataset.newLink + '/link', { method: 'POST', json: {} });
        if (!result.ok) {
            toast(result.error.message, 'danger');
            return;
        }
        output.replaceChildren();
        const note = document.createElement('p');
        note.className = 'mb-1';
        note.textContent = result.message;
        const group = document.createElement('div');
        group.className = 'input-group input-group-sm mb-2';
        const input = document.createElement('input');
        input.className = 'form-control font-monospace';
        input.readOnly = true;
        input.value = result.url;
        input.setAttribute('aria-label', t('signature.new_link'));
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'btn btn-outline-secondary';
        copy.dataset.copy = result.url;
        copy.textContent = t('signature.copy_link');
        group.append(input, copy);
        output.append(note, group);
    }));
});
