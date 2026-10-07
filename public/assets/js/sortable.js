// Fare ve dokunmatik için pointer tabanlı sürükle-bırak sıralama (HTML5 DnD mobilde güvenilir değil).
// Klavye erişimi için ayrıca taşıma butonları kullanılır; bu modül yalnızca işaretçi etkileşimini sağlar.

/**
 * @param {HTMLElement} container  Öğelerin doğrudan ebeveyni
 * @param {object} options
 * @param {string} options.items   Sıralanabilir öğe seçicisi
 * @param {string} options.handle  Sürükleme tutamacı seçicisi (öğenin içinde)
 * @param {(item: HTMLElement) => void} options.onEnd  Bırakıldığında
 */
export function makeSortable(container, { items, handle, onEnd = () => {} }) {
    let dragging = null;
    let pointerId = null;

    container.addEventListener('pointerdown', (event) => {
        const grip = event.target.closest(handle);
        if (!grip || !container.contains(grip) || event.button > 0) {
            return;
        }
        dragging = grip.closest(items);
        if (!dragging) {
            return;
        }
        event.preventDefault();
        pointerId = event.pointerId;
        grip.setPointerCapture(pointerId);
        dragging.classList.add('is-dragging');
        container.classList.add('is-sorting');
    });

    container.addEventListener('pointermove', (event) => {
        if (!dragging || event.pointerId !== pointerId) {
            return;
        }
        // Sürüklenen öğe hit-test'e girmesin
        dragging.style.pointerEvents = 'none';
        const below = document.elementFromPoint(event.clientX, event.clientY)?.closest(items);
        dragging.style.pointerEvents = '';
        if (!below || below === dragging || !container.contains(below)) {
            return;
        }
        const rect = below.getBoundingClientRect();
        const horizontal = getComputedStyle(container).display.includes('grid') || getComputedStyle(container).flexDirection === 'row';
        const after = horizontal
            ? event.clientX > rect.left + rect.width / 2
            : event.clientY > rect.top + rect.height / 2;
        below[after ? 'after' : 'before'](dragging);
    });

    const finish = (event) => {
        if (!dragging || event.pointerId !== pointerId) {
            return;
        }
        const item = dragging;
        dragging.classList.remove('is-dragging');
        container.classList.remove('is-sorting');
        dragging = null;
        pointerId = null;
        onEnd(item);
    };
    container.addEventListener('pointerup', finish);
    container.addEventListener('pointercancel', finish);
}

/**
 * Öğeyi bir adım taşır (klavye / buton). direction: -1 önce, +1 sonra.
 */
export function moveItem(item, direction) {
    const sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) {
        return false;
    }
    sibling[direction < 0 ? 'before' : 'after'](item);
    return true;
}
