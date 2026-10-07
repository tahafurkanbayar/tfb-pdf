// İmza İste: imzalayanlar, sayfa üzerinde imza alanları, davet bağlantıları.
import { api, config, t, tc } from '../app.js';
import { openPdf, renderPage } from '../pdf-preview.js';
import { renderStatus } from '../upload.js';
import { setupSource } from './source.js';
import { iconButton, renderPageCards } from './page-cards.js';

const page = document.querySelector('[data-tool="sign"]');
const maxSigners = Number(page.dataset.maxSigners) || 5;
const editorArea = page.querySelector('[data-editor-area]');
const signersBox = page.querySelector('[data-signers]');
const addSignerButton = page.querySelector('[data-add-signer]');
const grid = page.querySelector('[data-grid]');
const runButton = page.querySelector('[data-run]');
const runStatus = page.querySelector('[data-run-status]');
const linksSection = page.querySelector('[data-links]');

const modalElement = document.getElementById('field-editor');
const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
const canvas = modalElement.querySelector('[data-editor-canvas]');
const overlay = modalElement.querySelector('[data-overlay]');
const stage = modalElement.querySelector('[data-stage]');

let source = null;
let pdfPromise = null;
let editingPage = null;
let nextId = 1;
const signers = []; // { id, row }
const fields = [];  // { signer, page, x, y, w, h }

const activeSigner = () => Number(signersBox.querySelector('input[name="active-signer"]:checked')?.value ?? 0);
const signerName = (id) => signers.find((s) => s.id === id)?.row.querySelector('[data-name]').value.trim() || '#' + id;
const signerIndex = (id) => signers.findIndex((s) => s.id === id);

function addSigner() {
    if (signers.length >= maxSigners) {
        return;
    }
    const id = nextId++;
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end mb-2 signer-row';
    row.dataset.color = String(signers.length % 5);
    row.innerHTML = `
        <div class="col-auto"><input class="form-check-input mt-0" type="radio" name="active-signer" value="${id}" aria-label=""></div>
        <div class="col-sm-5"><label class="form-label small" for="signer-name-${id}"></label><input class="form-control form-control-sm" id="signer-name-${id}" data-name maxlength="150"></div>
        <div class="col-sm-4"><label class="form-label small" for="signer-email-${id}"></label><input class="form-control form-control-sm" type="email" id="signer-email-${id}" data-email maxlength="254"></div>
        <div class="col-auto" data-remove-slot></div>`;
    row.querySelector('input[type="radio"]').setAttribute('aria-label', t('signature.active_signer'));
    row.querySelector(`label[for="signer-name-${id}"]`).textContent = t('signature.signer_name');
    row.querySelector(`label[for="signer-email-${id}"]`).textContent = t('signature.signer_email');
    const remove = iconButton('trash', t('signature.remove_signer'), 'btn btn-sm btn-outline-danger');
    remove.addEventListener('click', () => {
        signers.splice(signerIndex(id), 1);
        for (let i = fields.length - 1; i >= 0; i--) {
            if (fields[i].signer === id) {
                fields.splice(i, 1);
            }
        }
        row.remove();
        if (!signersBox.querySelector('input[name="active-signer"]:checked')) {
            signersBox.querySelector('input[name="active-signer"]')?.click();
        }
        refresh();
    });
    row.querySelector('[data-remove-slot]').append(remove);
    row.querySelector('[data-name]').addEventListener('input', refresh);
    signersBox.append(row);
    signers.push({ id, row });
    row.querySelector('input[type="radio"]').checked = true;
    refresh();
}

function boxElement(field) {
    const el = document.createElement('div');
    el.className = 'sign-field signer-color-' + (signerIndex(field.signer) % 5);
    Object.assign(el.style, { left: field.x * 100 + '%', top: field.y * 100 + '%', width: field.w * 100 + '%', height: field.h * 100 + '%' });
    el.title = signerName(field.signer);
    return el;
}

function refresh() {
    addSignerButton.disabled = signers.length >= maxSigners;
    grid.querySelectorAll('.page-card').forEach((card) => {
        const number = Number(card.dataset.page);
        const pageFields = fields.filter((f) => f.page === number);
        card.querySelector('[data-thumb-boxes]').replaceChildren(...pageFields.map(boxElement));
        const badge = card.querySelector('[data-count]');
        badge.hidden = pageFields.length === 0;
        badge.textContent = tc('signature.field_count', pageFields.length);
    });
}

function renderOverlay() {
    overlay.replaceChildren();
    fields.forEach((field) => {
        if (field.page !== editingPage) {
            return;
        }
        const el = boxElement(field);
        el.textContent = signerName(field.signer);
        const remove = iconButton('x-circle', t('signature.remove_field'), 'btn btn-sm btn-light redact-box-remove');
        remove.addEventListener('pointerdown', (e) => e.stopPropagation());
        remove.addEventListener('click', () => {
            fields.splice(fields.indexOf(field), 1);
            renderOverlay();
            refresh();
        });
        el.append(remove);
        overlay.append(el);
    });
}

async function openEditor(number) {
    if (!activeSigner()) {
        addSigner();
    }
    editingPage = number;
    modalElement.querySelector('[data-editor-title]').textContent = t('signature.editor_title', { number });
    modalElement.querySelector('[data-editor-signer]').textContent = t('signature.active_signer') + ': ' + signerName(activeSigner());
    modal.show();
    pdfPromise ??= openPdf(`${config.apiUrl}/documents/${source.documentId}/versions/${source.version}/download?inline=1`);
    const width = Math.min(900, (modalElement.querySelector('.modal-body').clientWidth || 800) - 32);
    await renderPage(await pdfPromise, number, { maxWidth: width, maxHeight: window.innerHeight * 0.7, canvas });
    stage.style.width = canvas.width / Math.min(window.devicePixelRatio || 1, 2) + 'px';
    renderOverlay();
}

let drawing = null;
overlay.addEventListener('pointerdown', (event) => {
    const rect = overlay.getBoundingClientRect();
    drawing = { x: (event.clientX - rect.left) / rect.width, y: (event.clientY - rect.top) / rect.height, el: document.createElement('div') };
    drawing.el.className = 'sign-field';
    overlay.append(drawing.el);
    try { overlay.setPointerCapture(event.pointerId); } catch { /* desteklenmiyor */ }
    event.preventDefault();
});
overlay.addEventListener('pointermove', (event) => {
    if (!drawing) {
        return;
    }
    const rect = overlay.getBoundingClientRect();
    const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
    const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
    drawing.box = { x: Math.min(x, drawing.x), y: Math.min(y, drawing.y), w: Math.abs(x - drawing.x), h: Math.abs(y - drawing.y) };
    Object.assign(drawing.el.style, { left: drawing.box.x * 100 + '%', top: drawing.box.y * 100 + '%', width: drawing.box.w * 100 + '%', height: drawing.box.h * 100 + '%' });
});
const finishDrawing = () => {
    if (!drawing) {
        return;
    }
    if (drawing.box && drawing.box.w >= 0.02 && drawing.box.h >= 0.01) {
        fields.push({ signer: activeSigner(), page: editingPage, ...drawing.box });
    }
    drawing = null;
    renderOverlay();
    refresh();
};
overlay.addEventListener('pointerup', finishDrawing);
overlay.addEventListener('pointercancel', finishDrawing);

function buildControls(card, number, controls) {
    const handle = card.querySelector('.page-card-handle');
    handle.classList.add('is-static');
    const layer = document.createElement('div');
    layer.className = 'redact-thumb-boxes';
    layer.dataset.thumbBoxes = '';
    handle.append(layer);
    const badge = document.createElement('span');
    badge.className = 'page-card-badge badge text-bg-primary';
    badge.dataset.count = '';
    badge.hidden = true;
    handle.append(badge);
    const add = iconButton('pen', t('signature.add_field', { number }), 'btn btn-sm btn-outline-primary');
    add.addEventListener('click', () => openEditor(number));
    handle.addEventListener('click', () => openEditor(number));
    controls.append(add);
}

setupSource(page.querySelector('[data-source]'), async (selected) => {
    source = selected;
    pdfPromise = null;
    fields.length = 0;
    editorArea.hidden = !selected;
    linksSection.hidden = true;
    if (selected) {
        if (signers.length === 0) {
            addSigner();
        }
        await renderPageCards(grid, selected, buildControls);
    }
    refresh();
});

addSignerButton.addEventListener('click', addSigner);

runButton.addEventListener('click', async () => {
    const payload = {
        document: source.documentId,
        version: source.version,
        message: page.querySelector('#sign-message').value,
        signers: signers.map((s) => ({
            name: s.row.querySelector('[data-name]').value.trim(),
            email: s.row.querySelector('[data-email]').value.trim(),
            fields: fields.filter((f) => f.signer === s.id).map(({ page: p, x, y, w, h }) => ({ page: p, x, y, w, h })),
        })),
    };
    runButton.disabled = true;
    renderStatus(runStatus, 'processing', t('states.loading'));
    const result = await api('/signatures', { method: 'POST', json: payload });
    runButton.disabled = false;
    if (!result.ok) {
        renderStatus(runStatus, 'error', result.error.message);
        return;
    }
    runStatus.replaceChildren();
    editorArea.hidden = true;

    const list = linksSection.querySelector('[data-link-list]');
    list.replaceChildren(...result.links.map((link) => {
        const li = document.createElement('li');
        li.className = 'mb-3';
        const name = document.createElement('div');
        name.className = 'fw-semibold';
        name.textContent = link.name + (link.email ? ' <' + link.email + '>' : '');
        const group = document.createElement('div');
        group.className = 'input-group input-group-sm';
        const input = document.createElement('input');
        input.className = 'form-control font-monospace';
        input.readOnly = true;
        input.value = link.url;
        input.setAttribute('aria-label', link.name);
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'btn btn-outline-secondary';
        copy.dataset.copy = link.url;
        copy.textContent = t('signature.copy_link');
        group.append(input, copy);
        const note = document.createElement('div');
        note.className = 'small ' + (link.emailed ? 'text-success' : 'text-body-secondary');
        note.textContent = link.emailed ? t('signature.emailed') : t('signature.not_emailed');
        li.append(name, group, note);
        return li;
    }));
    linksSection.querySelector('[data-expires]').textContent = result.expires_note;
    linksSection.querySelector('[data-open-document]').href = `${config.baseUrl}${config.locale}/documents/${source.documentId}`;
    linksSection.hidden = false;
    linksSection.focus();
});
