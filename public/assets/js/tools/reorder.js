// Sayfaları Düzenle: sürükle-bırak / ok butonlarıyla sıralama ve sayfa kaldırma.
import { t } from '../app.js';
import { makeSortable, moveItem } from '../sortable.js';
import { setupSource } from './source.js';
import { iconButton, renderPageCards } from './page-cards.js';
import { announce, runOperation } from './common.js';

const page = document.querySelector('[data-tool="reorder"]');
const section = page.querySelector('[data-pages-section]');
const grid = page.querySelector('[data-sort-grid]');
const summary = page.querySelector('[data-summary]');
const runButton = page.querySelector('[data-run]');
const resetButton = page.querySelector('[data-reset]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');
const announcer = page.querySelector('[data-announcer]');

let source = null;

const cards = () => [...grid.querySelectorAll('.page-card')];

function currentOrder() {
    return cards().filter((c) => !c.classList.contains('is-removed')).map((c) => Number(c.dataset.page));
}

function refresh() {
    const all = cards();
    const order = currentOrder();
    const removed = all.length - order.length;
    const unchanged = removed === 0 && order.every((p, i) => p === i + 1);

    if (order.length === 0) {
        summary.textContent = t('reorder.all_removed');
        summary.classList.add('text-danger');
    } else {
        summary.classList.remove('text-danger');
        summary.textContent = unchanged ? t('reorder.unchanged') : t('reorder.summary', { kept: order.length, removed });
    }
    runButton.disabled = order.length === 0 || unchanged;

    all.forEach((card, index) => {
        card.querySelector('[data-left]').disabled = index === 0;
        card.querySelector('[data-right]').disabled = index === all.length - 1;
    });
}

function announceMove(card) {
    announce(announcer, t('reorder.moved', { number: card.dataset.page, position: cards().indexOf(card) + 1 }));
}

function buildControls(card, number, controls) {
    const left = iconButton('chevron-left', t('common.move_left'));
    left.dataset.left = '';
    const right = iconButton('chevron-right', t('common.move_right'));
    right.dataset.right = '';
    const remove = iconButton('trash', t('reorder.remove_page', { number }));
    remove.setAttribute('aria-pressed', 'false');

    const badge = document.createElement('span');
    badge.className = 'page-card-badge badge text-bg-danger';
    badge.textContent = t('reorder.removed_badge');
    card.querySelector('.page-card-handle').append(badge);

    const move = (direction, button) => {
        if (moveItem(card, direction)) {
            announceMove(card);
            refresh();
            (button.disabled ? card.querySelector(direction < 0 ? '[data-right]' : '[data-left]') : button).focus();
        }
    };
    left.addEventListener('click', () => move(-1, left));
    right.addEventListener('click', () => move(1, right));
    remove.addEventListener('click', () => {
        const removed = card.classList.toggle('is-removed');
        remove.setAttribute('aria-pressed', String(removed));
        const label = t(removed ? 'reorder.restore_page' : 'reorder.remove_page', { number });
        remove.setAttribute('aria-label', label);
        remove.title = label;
        refresh();
    });

    controls.append(left, remove, right);
}

async function load(selected) {
    source = selected;
    section.hidden = !selected;
    resultSection.hidden = true;
    runStatus.replaceChildren();
    if (!selected) {
        return;
    }
    await renderPageCards(grid, selected, buildControls);
    refresh();
}

makeSortable(grid, {
    items: '.page-card',
    handle: '.page-card-handle',
    onEnd: (card) => {
        announceMove(card);
        refresh();
    },
});

setupSource(page.querySelector('[data-source]'), load);

resetButton.addEventListener('click', () => load(source));

runButton.addEventListener('click', async () => {
    const result = await runOperation('reorder', {
        document: source.documentId,
        version: source.version,
        order: currentOrder(),
    }, { button: runButton, status: runStatus, resultSection });
    if (result) {
        refresh();
    }
});
