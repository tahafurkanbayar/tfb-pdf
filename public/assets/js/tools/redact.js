// Bilgileri Karart: sayfa üzerinde alan seçimi, gerekirse tarayıcıda sayfa görüntüsü üretimi, kalıcı karartma.
import { config, t, tc } from '../app.js';
import { openPdf, renderPage } from '../pdf-preview.js';
import { renderStatus } from '../upload.js';
import { setupSource } from './source.js';
import { iconButton, renderPageCards } from './page-cards.js';
import { runOperation } from './common.js';

const page = document.querySelector('[data-tool="redact"]');
const serverRender = page.dataset.serverRender === '1';
const dpi = Number(page.dataset.dpi) || 150;
const section = page.querySelector('[data-pages-section]');
const grid = page.querySelector('[data-grid]');
const summary = page.querySelector('[data-summary]');
const runButton = page.querySelector('[data-run]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');

const modalElement = document.getElementById('redact-editor');
const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
const stage = modalElement.querySelector('[data-stage]');
const canvas = modalElement.querySelector('[data-editor-canvas]');
const overlay = modalElement.querySelector('[data-overlay]');

let source = null;
let pdfPromise = null;
let editingPage = null;
const boxes = new Map(); // sayfa → [[x, y, w, h]] (0..1, görünen sayfaya göre)

const pdf = () => (pdfPromise ??= openPdf(`${config.apiUrl}/documents/${source.documentId}/versions/${source.version}/download?inline=1`));

function refresh() {
    const pages = [...boxes.entries()].filter(([, list]) => list.length > 0);
    const total = pages.reduce((n, [, list]) => n + list.length, 0);
    summary.textContent = total === 0 ? t('redact.nothing_selected') : t('redact.summary', { pages: pages.length, boxes: total });
    runButton.disabled = total === 0;
    grid.querySelectorAll('.page-card').forEach((card) => {
        const count = (boxes.get(Number(card.dataset.page)) ?? []).length;
        const badge = card.querySelector('[data-count]');
        badge.hidden = count === 0;
        badge.textContent = tc('redact.box_count', count);
        card.classList.toggle('has-redactions', count > 0);
        drawThumbBoxes(card);
    });
}

function drawThumbBoxes(card) {
    const layer = card.querySelector('[data-thumb-boxes]');
    layer.replaceChildren(...(boxes.get(Number(card.dataset.page)) ?? []).map(boxElement));
}

function boxElement([x, y, w, h]) {
    const el = document.createElement('div');
    el.className = 'redact-box';
    Object.assign(el.style, { left: x * 100 + '%', top: y * 100 + '%', width: w * 100 + '%', height: h * 100 + '%' });
    return el;
}

function renderOverlay() {
    overlay.replaceChildren();
    (boxes.get(editingPage) ?? []).forEach((box, index) => {
        const el = boxElement(box);
        const remove = iconButton('x-circle', t('redact.remove_box'), 'btn btn-sm btn-light redact-box-remove');
        remove.addEventListener('pointerdown', (e) => e.stopPropagation());
        remove.addEventListener('click', () => {
            boxes.get(editingPage).splice(index, 1);
            renderOverlay();
            refresh();
        });
        el.append(remove);
        overlay.append(el);
    });
}

async function openEditor(number) {
    editingPage = number;
    modalElement.querySelector('[data-editor-title]').textContent = t('redact.editor_title', { number });
    overlay.replaceChildren();
    modal.show();
    const width = Math.min(900, (modalElement.querySelector('.modal-body').clientWidth || 800) - 32);
    await renderPage(await pdf(), number, { maxWidth: width, maxHeight: window.innerHeight * 0.7, canvas });
    // Görüntülenen boyut CSS ile; kutular yüzde olarak konumlanır
    stage.style.width = canvas.width / Math.min(window.devicePixelRatio || 1, 2) + 'px';
    renderOverlay();
}

// Sürükleyerek kutu çizimi (fare + dokunmatik)
let drawing = null;
overlay.addEventListener('pointerdown', (event) => {
    if (event.button > 0) {
        return;
    }
    const rect = overlay.getBoundingClientRect();
    drawing = { x: (event.clientX - rect.left) / rect.width, y: (event.clientY - rect.top) / rect.height, el: boxElement([0, 0, 0, 0]) };
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
    drawing.box = [Math.min(x, drawing.x), Math.min(y, drawing.y), Math.abs(x - drawing.x), Math.abs(y - drawing.y)];
    Object.assign(drawing.el.style, { left: drawing.box[0] * 100 + '%', top: drawing.box[1] * 100 + '%', width: drawing.box[2] * 100 + '%', height: drawing.box[3] * 100 + '%' });
});
const finish = () => {
    if (!drawing) {
        return;
    }
    if (drawing.box && drawing.box[2] > 0.005 && drawing.box[3] > 0.005) {
        if (!boxes.has(editingPage)) {
            boxes.set(editingPage, []);
        }
        boxes.get(editingPage).push(drawing.box);
    }
    drawing = null;
    renderOverlay();
    refresh();
};
overlay.addEventListener('pointerup', finish);
overlay.addEventListener('pointercancel', finish);

modalElement.querySelector('[data-whole-page]').addEventListener('click', () => {
    boxes.set(editingPage, [[0, 0, 1, 1]]);
    renderOverlay();
    refresh();
});
modalElement.querySelector('[data-clear]').addEventListener('click', () => {
    boxes.set(editingPage, []);
    renderOverlay();
    refresh();
});

function buildControls(card, number, controls) {
    const handle = card.querySelector('.page-card-handle');
    handle.classList.add('is-static');
    const layer = document.createElement('div');
    layer.className = 'redact-thumb-boxes';
    layer.dataset.thumbBoxes = '';
    handle.append(layer);

    const badge = document.createElement('span');
    badge.className = 'page-card-badge badge text-bg-dark';
    badge.dataset.count = '';
    badge.hidden = true;
    handle.append(badge);

    const edit = iconButton('eraser', t('redact.edit_page', { number }), 'btn btn-sm btn-outline-danger');
    edit.addEventListener('click', () => openEditor(number));
    handle.addEventListener('click', () => openEditor(number));
    controls.append(edit);
}

/**
 * Sunucuda Ghostscript yoksa: karartılacak sayfaları tarayıcıda DPI çözünürlüğünde çiz, kutuları uygula, JPEG yap.
 */
async function browserImages(form) {
    const doc = await pdf();
    const pages = [...boxes.entries()].filter(([, list]) => list.length > 0);
    let index = 0;
    for (const [number, list] of pages) {
        renderStatus(runStatus, 'processing', t('redact.preparing', { current: ++index, total: pages.length }));
        const pdfPage = await doc.getPage(number);
        const viewport = pdfPage.getViewport({ scale: 1 });
        const scale = dpi / 72;
        const target = await renderPage(doc, number, {
            maxWidth: Math.round(viewport.width * scale),
            maxHeight: Math.round(viewport.height * scale),
            pixelRatio: 1,
        });
        const context = target.getContext('2d');
        context.fillStyle = '#000';
        for (const [x, y, w, h] of list) {
            context.fillRect(x * target.width - 1, y * target.height - 1, w * target.width + 2, h * target.height + 2);
        }
        const blob = await new Promise((resolve) => target.toBlob(resolve, 'image/jpeg', 0.9));
        form.append('page_' + number, blob, 'page-' + number + '.jpg');
        target.width = 0;
        target.height = 0;
    }
}

setupSource(page.querySelector('[data-source]'), async (selected) => {
    source = selected;
    pdfPromise = null;
    boxes.clear();
    section.hidden = !selected;
    resultSection.hidden = true;
    runStatus.replaceChildren();
    if (selected) {
        await renderPageCards(grid, selected, buildControls);
    }
    refresh();
});

runButton.addEventListener('click', async () => {
    if (!window.confirm(t('redact.confirm'))) {
        return;
    }
    const payload = Object.fromEntries([...boxes.entries()].filter(([, list]) => list.length > 0));
    const form = new FormData();
    form.append('document', source.documentId);
    form.append('version', String(source.version));
    form.append('boxes', JSON.stringify(payload));

    runButton.disabled = true;
    try {
        if (!serverRender) {
            await browserImages(form);
        }
    } catch {
        renderStatus(runStatus, 'error', t('preview.failed'));
        runButton.disabled = false;
        return;
    }
    await runOperation('redact', form, { button: runButton, status: runStatus, resultSection });
});
