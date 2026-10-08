// PDF Birleştir: çoklu yükleme, sıralama (sürükle-bırak + butonlar), kaldırma, önizleme, birleştirme.
import { icon, t, tc } from '../app.js';
import { bindDropzone, uploadFile } from '../upload.js';
import { makeSortable, moveItem } from '../sortable.js';
import { ThumbnailSource } from '../pdf-preview.js';
import { announce, runOperation } from './common.js';

const page = document.querySelector('[data-tool="merge"]');
const list = page.querySelector('[data-file-list]');
const empty = page.querySelector('[data-empty]');
const runButton = page.querySelector('[data-run]');
const runHint = page.querySelector('[data-run-hint]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');
const announcer = page.querySelector('[data-announcer]');

const items = new Map(); // li => { document, version, name, ready }

function iconButton(name, label, className = 'btn btn-sm btn-outline-secondary') {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.setAttribute('aria-label', label);
    button.title = label;
    button.append(icon(name));
    return button;
}

function refresh() {
    const ready = [...items.values()].filter((i) => i.ready).length;
    const busy = [...items.values()].some((i) => !i.ready && !i.failed);
    empty.hidden = items.size > 0;
    runButton.disabled = ready < 2 || busy;
    runHint.hidden = ready >= 2;
    [...list.children].forEach((li, index) => {
        li.querySelector('[data-position]').textContent = String(index + 1);
        li.querySelector('[data-up]').disabled = index === 0;
        li.querySelector('[data-down]').disabled = index === list.children.length - 1;
    });
}

function position(li) {
    return [...list.children].indexOf(li) + 1;
}

function createItem(name) {
    const li = document.createElement('li');
    li.className = 'file-item card mb-2';

    const body = document.createElement('div');
    body.className = 'card-body d-flex align-items-center gap-2 gap-sm-3 py-2';

    const grip = iconButton('grip-vertical', t('tools.ui.drag_handle'), 'btn btn-sm btn-link text-body-secondary drag-handle');
    const pos = document.createElement('span');
    pos.className = 'badge badge-soft';
    pos.dataset.position = '';

    const thumb = document.createElement('span');
    thumb.className = 'file-thumb is-loading';
    const img = document.createElement('img');
    img.alt = '';
    thumb.append(img);

    const info = document.createElement('div');
    info.className = 'flex-grow-1 min-w-0';
    const title = document.createElement('div');
    title.className = 'fw-semibold text-truncate';
    title.textContent = name;
    const meta = document.createElement('div');
    meta.className = 'small text-body-secondary';
    meta.dataset.meta = '';
    meta.textContent = t('upload.uploading');
    const progress = document.createElement('progress');
    progress.className = 'upload-progress w-100';
    progress.max = 100;
    progress.value = 0;
    progress.setAttribute('aria-label', t('upload.uploading_named', { name }));
    info.append(title, meta, progress);

    const controls = document.createElement('div');
    controls.className = 'd-flex gap-1 flex-shrink-0';
    const up = iconButton('chevron-up', t('common.move_up'));
    up.dataset.up = '';
    const down = iconButton('chevron-down', t('common.move_down'));
    down.dataset.down = '';
    const remove = iconButton('trash', t('tools.ui.remove_file', { name }), 'btn btn-sm btn-outline-danger');
    controls.append(up, down, remove);

    body.append(grip, pos, thumb, info, controls);
    li.append(body);

    up.addEventListener('click', () => move(li, -1, up));
    down.addEventListener('click', () => move(li, 1, down));
    remove.addEventListener('click', () => {
        const next = li.nextElementSibling ?? li.previousElementSibling;
        items.delete(li);
        li.remove();
        announce(announcer, t('tools.ui.removed', { name }));
        refresh();
        (next?.querySelector('[data-up]') ?? runButton).focus();
    });

    list.append(li);
    items.set(li, { name, ready: false, failed: false });
    refresh();
    return { li, img, thumb, meta, progress };
}

function move(li, direction, focusTarget) {
    if (moveItem(li, direction)) {
        announce(announcer, t('tools.ui.moved', { name: items.get(li).name, position: position(li) }));
        refresh();
        // Buton devre dışı kaldıysa odak kaybolmasın
        (focusTarget.disabled ? li.querySelector(direction < 0 ? '[data-down]' : '[data-up]') : focusTarget).focus();
    }
}

async function markReady(parts, documentId, version, pages) {
    const state = items.get(parts.li);
    if (!state) {
        return; // Yükleme sürerken kaldırıldı
    }
    Object.assign(state, { document: documentId, version, ready: true });
    parts.progress.remove();
    parts.meta.textContent = [pages ? tc('common.page_count', pages) : null, t('tools.ui.ready')].filter(Boolean).join(' · ');
    refresh();

    try {
        const source = await new ThumbnailSource({ documentId, version, maxDimension: 120 }).init();
        parts.img.src = await source.url(1);
        parts.thumb.classList.remove('is-loading');
    } catch {
        parts.thumb.classList.remove('is-loading');
    }
}

async function addFile(file) {
    const parts = createItem(file.name);
    const result = await uploadFile(file, { onProgress: (p) => { parts.progress.value = p; } });
    if (!items.has(parts.li)) {
        return;
    }
    if (!result.ok) {
        items.get(parts.li).failed = true;
        parts.progress.remove();
        parts.meta.className = 'small text-danger';
        parts.meta.textContent = t('tools.ui.upload_failed', { name: file.name, reason: result.error.message });
        parts.thumb.classList.remove('is-loading');
        refresh();
        return;
    }
    const original = result.document.versions[0];
    await markReady(parts, result.document.id, original.number, original.pages);
}

bindDropzone(page.querySelector('[data-dropzone]'), (files) => {
    // Sırayla yükle: paylaşımlı hostingde eşzamanlı büyük yüklemelerden kaçın
    [...files].reduce((chain, file) => chain.then(() => addFile(file)), Promise.resolve());
});

makeSortable(list, {
    items: '.file-item',
    handle: '.drag-handle',
    onEnd: (li) => {
        announce(announcer, t('tools.ui.moved', { name: items.get(li)?.name ?? '', position: position(li) }));
        refresh();
    },
});

const preselected = page.dataset.preselected ? JSON.parse(page.dataset.preselected) : null;
if (preselected) {
    const parts = createItem(preselected.name);
    markReady(parts, preselected.id, preselected.version, preselected.pages);
}

runButton.addEventListener('click', () => {
    const payload = {
        items: [...list.children]
            .map((li) => items.get(li))
            .filter((item) => item?.ready)
            .map((item) => ({ document: item.document, version: item.version })),
    };
    runOperation('merge', payload, { button: runButton, status: runStatus, resultSection });
});
