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
