// Sayfa aralığı ayrıştırıcı — sunucudaki App\Pdf\PageRangeParser ile aynı kurallar.
// Geçersiz aralıklar işlem başlamadan tarayıcıda yakalanır; sunucu yine de yeniden doğrular.

/**
 * @returns {{ ranges: Array<[number, number]> } | { error: string, replace: object }}
 */
export function parsePageRanges(input, totalPages) {
    const text = String(input).trim();
    if (text === '') {
        return { error: 'split.range_empty', replace: {} };
    }

    const ranges = [];
    for (const raw of text.split(/[,;\n\r]+/)) {
        const token = raw.replace(/\s+/g, '');
        if (token === '') {
            continue;
        }

        let start;
        let end;
        let m;
        if ((m = token.match(/^(\d{1,6})$/))) {
            start = end = Number(m[1]);
        } else if ((m = token.match(/^(\d{1,6})[-–](\d{1,6})?$/))) {
            start = Number(m[1]);
            end = m[2] !== undefined && m[2] !== '' ? Number(m[2]) : totalPages;
        } else {
            return { error: 'split.range_invalid', replace: { token: token.slice(0, 20) } };
        }

        if (start < 1 || end < 1) {
            return { error: 'split.range_zero', replace: { token } };
        }
        if (start > end) {
            return { error: 'split.range_reversed', replace: { token } };
        }
        if (end > totalPages) {
            return { error: 'split.range_out_of_bounds', replace: { token, total: totalPages } };
        }
        ranges.push([start, end]);
    }

    return ranges.length === 0 ? { error: 'split.range_empty', replace: {} } : { ranges };
}

/**
 * Seçili sayfa kümesini kısa aralık ifadesine çevirir: [1,2,3,5] → "1-3, 5"
 */
export function pagesToRangeText(pages) {
    const sorted = [...new Set(pages)].sort((a, b) => a - b);
    const parts = [];
    let start = null;
    let prev = null;
    for (const page of sorted) {
        if (start === null) {
            start = prev = page;
        } else if (page === prev + 1) {
            prev = page;
        } else {
            parts.push(start === prev ? String(start) : `${start}-${prev}`);
            start = prev = page;
        }
    }
    if (start !== null) {
        parts.push(start === prev ? String(start) : `${start}-${prev}`);
    }
    return parts.join(', ');
}
