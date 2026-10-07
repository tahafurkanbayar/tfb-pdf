// PDF Böl: mod seçimi, aralık doğrulama (işlemden önce), küçük resimden sayfa seçimi.
import { t, tc } from '../app.js';
import { parsePageRanges, pagesToRangeText } from '../page-range.js';
import { renderPageGrid, setupSource } from './source.js';
import { runOperation } from './common.js';

const page = document.querySelector('[data-tool="split"]');
const form = page.querySelector('[data-settings]');
const rangesInput = form.querySelector('#ranges');
const rangesGroup = form.querySelector('[data-ranges-group]');
const rangesError = form.querySelector('[data-ranges-error]');
const outputsCount = form.querySelector('[data-outputs-count]');
const runButton = form.querySelector('[data-run]');
const runStatus = form.querySelector('[data-run-status]');
const pagesSection = page.querySelector('[data-pages-section]');
const grid = page.querySelector('[data-page-grid]');
const resultSection = page.querySelector('[data-result]');

let source = null;

const mode = () => form.querySelector('input[name="mode"]:checked').value;

/**
 * Formu doğrular, hata ve oluşacak dosya sayısını gösterir. Geçerliyse aralıkları döndürür.
 */
function validate({ showErrors = true } = {}) {
    const currentMode = mode();
    rangesGroup.hidden = currentMode === 'each';
    page.querySelector('[data-select-hint]').hidden = currentMode === 'each';

    if (!source) {
        runButton.disabled = true;
        outputsCount.textContent = '';
        return null;
    }

    let count;
    let ranges = [];
    if (currentMode === 'each') {
        count = source.pages;
        rangesInput.classList.remove('is-invalid');
    } else {
        const parsed = parsePageRanges(rangesInput.value, source.pages);
        if (parsed.error) {
            const empty = rangesInput.value.trim() === '';
            rangesInput.classList.toggle('is-invalid', showErrors && !empty);
            rangesInput.setAttribute('aria-invalid', String(showErrors && !empty));
            rangesError.textContent = t(parsed.error, parsed.replace);
            runButton.disabled = true;
            outputsCount.textContent = '';
            highlight([]);
            return null;
        }
        rangesInput.classList.remove('is-invalid');
        rangesInput.setAttribute('aria-invalid', 'false');
        ranges = parsed.ranges;
        count = currentMode === 'ranges' ? ranges.length : 1;
        highlight(ranges.flatMap(([a, b]) => Array.from({ length: b - a + 1 }, (_, i) => a + i)));
    }

    outputsCount.textContent = tc('split.outputs_count', count);
    runButton.disabled = currentMode === 'each' && source.pages < 2;
    return ranges;
}

function highlight(pages) {
    const selected = new Set(pages);
    grid.querySelectorAll('[data-page-number]').forEach((item) => {
        const on = selected.has(Number(item.dataset.pageNumber));
        item.classList.toggle('is-selected', on);
        item.setAttribute('aria-pressed', String(on));
    });
}

setupSource(page.querySelector('[data-source]'), async (selected) => {
    source = selected;
    pagesSection.hidden = !selected;
    resultSection.hidden = true;
    validate({ showErrors: false });
    await renderPageGrid(grid, selected, (item, number) => {
        item.setAttribute('aria-pressed', 'false');
        item.setAttribute('aria-label', t('split.select_page', { number }));
        item.addEventListener('click', () => {
            if (mode() === 'each') {
                return;
            }
            // Küçük resme tıklamak sayfayı seçime ekler/çıkarır ve aralık metnini günceller
            const parsed = parsePageRanges(rangesInput.value, source.pages);
            const pages = parsed.ranges ? parsed.ranges.flatMap(([a, b]) => Array.from({ length: b - a + 1 }, (_, i) => a + i)) : [];
            const set = new Set(pages);
            set.has(number) ? set.delete(number) : set.add(number);
            rangesInput.value = pagesToRangeText([...set]);
            validate();
        });
    });
    validate({ showErrors: false });
});

rangesInput.addEventListener('input', () => validate());
form.querySelectorAll('input[name="mode"]').forEach((radio) => radio.addEventListener('change', () => validate({ showErrors: false })));

form.addEventListener('submit', (event) => {
    event.preventDefault();
    const ranges = validate();
    if (ranges === null) {
        rangesInput.focus();
        return;
    }
    runOperation('split', {
        document: source.documentId,
        version: source.version,
        mode: mode(),
        ranges: rangesInput.value,
    }, { button: runButton, status: runStatus, resultSection });
});
