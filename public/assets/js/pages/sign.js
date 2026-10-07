// İmzalayan sayfası: belge önizlemesi (alanlar vurgulu), onay, çizim / yazılı imza, imzala / reddet.
import { api, config, t } from '../app.js';
import { openPdf, renderPage } from '../pdf-preview.js';

const root = document.querySelector('[data-sign]');
const token = root.dataset.token;
const fields = JSON.parse(root.dataset.fields || '[]');
const pagesContainer = root.querySelector('[data-pages]');

// --- Önizleme: tüm sayfalar (en fazla 30), imzalayanın alanları vurgulu
(async () => {
    try {
        const pdf = await openPdf(`${config.apiUrl}/sign/${token}/document`);
        const width = Math.min(760, pagesContainer.clientWidth || 700);
        for (let number = 1; number <= Math.min(pdf.numPages, 30); number++) {
            const wrapper = document.createElement('div');
            wrapper.className = 'sign-page';
            const canvas = await renderPage(pdf, number, { maxWidth: width, maxHeight: width * 1.5 });
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', t('preview.page_label', { number }));
            wrapper.append(canvas);
            for (const field of fields.filter((f) => f.page === number)) {
                const box = document.createElement('div');
                box.className = 'sign-field';
                Object.assign(box.style, { left: field.x * 100 + '%', top: field.y * 100 + '%', width: field.w * 100 + '%', height: field.h * 100 + '%' });
                wrapper.append(box);
            }
            pagesContainer.append(wrapper);
        }
    } catch {
        pagesContainer.textContent = t('preview.failed');
    }
})();

const form = root.querySelector('[data-sign-form]');
if (form) {
    const pad = form.querySelector('[data-pad]');
    const context = pad.getContext('2d');
    const status = form.querySelector('[data-status]');
    const submit = form.querySelector('[data-submit]');
    let hasInk = false;
    let drawing = false;

    context.lineWidth = 3;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#14287a';

    const point = (event) => {
        const rect = pad.getBoundingClientRect();
        return [(event.clientX - rect.left) * (pad.width / rect.width), (event.clientY - rect.top) * (pad.height / rect.height)];
    };
    pad.addEventListener('pointerdown', (event) => {
        drawing = true;
        try { pad.setPointerCapture(event.pointerId); } catch { /* desteklenmiyor */ }
        context.beginPath();
        context.moveTo(...point(event));
        event.preventDefault();
    });
    pad.addEventListener('pointermove', (event) => {
        if (!drawing) {
            return;
        }
        context.lineTo(...point(event));
        context.stroke();
        hasInk = true;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => pad.addEventListener(name, () => {
        drawing = false;
    }));
    form.querySelector('[data-clear]').addEventListener('click', () => {
        context.clearRect(0, 0, pad.width, pad.height);
        hasInk = false;
    });

    form.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', () => {
        const typed = form.querySelector('input[name="type"]:checked').value === 'typed';
        form.querySelector('[data-method-drawn]').hidden = typed;
        form.querySelector('[data-method-typed]').hidden = !typed;
    }));

    const show = (message, kind) => {
        status.className = 'mt-3 alert alert-' + kind;
        status.textContent = message;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const consent = form.querySelector('#consent');
        if (!consent.checked) {
            show(t('signature.consent_required'), 'warning');
            consent.focus();
            return;
        }
        const type = form.querySelector('input[name="type"]:checked').value;
        let signature;
        if (type === 'drawn') {
            if (!hasInk) {
                show(t('signature.draw_empty'), 'warning');
                return;
            }
            signature = pad.toDataURL('image/png');
        } else {
            signature = form.querySelector('#typed-name').value.trim();
        }

        submit.disabled = true;
        const result = await api('/sign/' + token, { method: 'POST', json: { consent: true, type, signature } });
        if (result.ok) {
            show(result.message, 'success');
            window.setTimeout(() => window.location.reload(), 1200);
        } else {
            submit.disabled = false;
            show(result.error.message, 'danger');
        }
    });

    form.querySelector('[data-decline]').addEventListener('click', async () => {
        if (!window.confirm(t('signature.decline_confirm'))) {
            return;
        }
        const result = await api('/sign/' + token + '/decline', { method: 'POST', json: { reason: form.querySelector('#decline-reason').value } });
        if (result.ok) {
            show(result.message, 'secondary');
            window.setTimeout(() => window.location.reload(), 1200);
        } else {
            show(result.error.message, 'danger');
        }
    });
}
