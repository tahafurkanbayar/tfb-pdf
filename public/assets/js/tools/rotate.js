// PDF Döndür: sayfa bazında veya toplu 90° adımlarla döndürme; önizleme anında döner.
import { t, tc } from '../app.js';
import { setupSource } from './source.js';
import { iconButton, renderPageCards } from './page-cards.js';
import { runOperation } from './common.js';

const page = document.querySelector('[data-tool="rotate"]');
const section = page.querySelector('[data-pages-section]');
const grid = page.querySelector('[data-grid]');
const summary = page.querySelector('[data-summary]');
const runButton = page.querySelector('[data-run]');
const runStatus = page.querySelector('[data-run-status]');
const resultSection = page.querySelector('[data-result]');

let source = null;
const rotations = new Map(); // sayfa → 0/90/180/270

function apply(card) {
    const number = Number(card.dataset.page);
    const degrees = rotations.get(number) ?? 0;
    // CSSOM ile stil (CSP style-src 'self' ile uyumlu; inline style özniteliği değil)
    card.querySelector('img').style.transform = `rotate(${degrees}deg)`;
    const badge = card.querySelector('[data-angle]');
    badge.hidden = degrees === 0;
    badge.textContent = t('rotate.angle', { degrees });
    card.querySelector('.page-card-handle').setAttribute('aria-label', degrees === 0 ? t('preview.page_label', { number }) : t('rotate.page_state', { number, degrees }));
}

function refresh() {
    const count = [...rotations.values()].filter((d) => d !== 0).length;
    summary.textContent = count === 0 ? t('rotate.unchanged') : tc('rotate.summary', count);
    runButton.disabled = count === 0;
}

function turn(number, delta) {
    rotations.set(number, (((rotations.get(number) ?? 0) + delta) % 360 + 360) % 360);
    apply(grid.querySelector(`.page-card[data-page="${number}"]`));
    refresh();
}

function buildControls(card, number, controls) {
    const badge = document.createElement('span');
    badge.className = 'page-card-badge badge text-bg-primary';
    badge.dataset.angle = '';
    badge.hidden = true;
    const handle = card.querySelector('.page-card-handle');
    handle.classList.add('is-static');
    handle.setAttribute('role', 'img');
    handle.append(badge);

    const left = iconButton('arrow-counterclockwise', t('rotate.left', { number }));
    const right = iconButton('arrow-clockwise', t('rotate.right', { number }));
    left.addEventListener('click', () => turn(number, -90));
    right.addEventListener('click', () => turn(number, 90));
    controls.append(left, right);
}

function all(delta) {
    grid.querySelectorAll('.page-card').forEach((card) => turn(Number(card.dataset.page), delta));
}

setupSource(page.querySelector('[data-source]'), async (selected) => {
    source = selected;
    rotations.clear();
    section.hidden = !selected;
    resultSection.hidden = true;
    runStatus.replaceChildren();
    if (selected) {
        await renderPageCards(grid, selected, buildControls);
    }
    refresh();
});

page.querySelector('[data-all-left]').addEventListener('click', () => all(-90));
page.querySelector('[data-all-right]').addEventListener('click', () => all(90));
page.querySelector('[data-reset]').addEventListener('click', () => {
    rotations.clear();
    grid.querySelectorAll('.page-card').forEach(apply);
    refresh();
});

runButton.addEventListener('click', () => {
    const payload = Object.fromEntries([...rotations.entries()].filter(([, d]) => d !== 0));
    runOperation('rotate', { document: source.documentId, version: source.version, rotations: payload },
        { button: runButton, status: runStatus, resultSection });
});
