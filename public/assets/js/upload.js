// Dosya yükleme: sürükle-bırak alanı ve ilerleme göstergeli XHR (fetch yükleme ilerlemesi vermez).
import { config, icon, t } from './app.js';

export function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let i = 0;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    // Sunucudaki Size::format ile aynı: tek ondalık, binlik ayırıcı yok, ondalık ayırıcı sayfa dilinden (TR "13,5 KB")
    const number = i === 0
        ? String(size)
        : new Intl.NumberFormat(config.locale, { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: false }).format(size);
    return number + ' ' + units[i];
}

/**
 * Seçilen dosyaların önizleme listesi ([data-dropzone-files]): ikon, ad, boyut ve her dosya için ilerleme çubuğu.
 * @returns {Array<{ item: HTMLElement, progress: HTMLProgressElement }>}
 */
export function renderFileChips(container, files) {
    container.replaceChildren();
    container.hidden = files.length === 0;
    return [...files].map((file) => {
        const item = document.createElement('li');
        item.className = 'file-chip';
        const iconBox = document.createElement('span');
        iconBox.className = 'file-chip-icon';
        iconBox.append(icon('file-earmark-pdf'));
        const info = document.createElement('div');
        info.className = 'min-w-0 flex-grow-1 d-flex flex-column gap-1';
        const name = document.createElement('span');
        name.className = 'file-chip-name';
        name.textContent = file.name;
        const size = document.createElement('span');
        size.className = 'small text-body-secondary';
        size.textContent = formatSize(file.size);
        const progress = document.createElement('progress');
        progress.className = 'upload-progress w-100';
        progress.max = 100;
        progress.value = 0;
        progress.setAttribute('aria-label', t('upload.uploading_named', { name: file.name }));
        info.append(name, size, progress);
        item.append(iconBox, info);
        container.append(item);
        return { item, progress };
    });
}

/**
 * Tek dosyayı /api/documents'a yükler.
 * @returns {Promise<object>} API yanıtı ({ ok, document } veya { ok:false, error })
 */
export function uploadFile(file, { onProgress = () => {}, office = false, expiry = null } = {}) {
    return new Promise((resolve) => {
        const form = new FormData();
        form.append('file', file);
        if (office) {
            form.append('office', '1');
        }
        if (expiry) {
            form.append('expiry', expiry);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', config.apiUrl + '/documents');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-Token', config.csrfToken);
        xhr.setRequestHeader('X-Locale', config.locale);
        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        });
        xhr.addEventListener('load', () => {
            let data = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                // 413 gibi sunucu (Apache/PHP) hataları JSON olmayabilir
            }
            if (data && typeof data === 'object') {
                resolve(data);
            } else if (xhr.status === 413) {
                resolve({ ok: false, error: { message: t('upload.server_limit'), recoverable: true } });
            } else {
                resolve({ ok: false, error: { message: t('errors.generic'), recoverable: true } });
            }
        });
        xhr.addEventListener('error', () => resolve({ ok: false, error: { message: t('errors.network'), recoverable: true } }));
        xhr.send(form);
    });
}

/**
 * [data-dropzone] alanını bağlar. onFiles(fileList) seçilen/bırakılan dosyalarla çağrılır.
 */
export function bindDropzone(zone, onFiles) {
    const input = zone.querySelector('[data-dropzone-input]');
    const stop = (event) => {
        event.preventDefault();
        event.stopPropagation();
    };

    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, (event) => {
        stop(event);
        zone.classList.add('is-dragover');
    }));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, (event) => {
        stop(event);
        zone.classList.remove('is-dragover');
    }));
    zone.addEventListener('drop', (event) => {
        if (event.dataTransfer?.files?.length) {
            onFiles(event.dataTransfer.files);
        }
    });
    input?.addEventListener('change', () => {
        if (input.files?.length) {
            onFiles(input.files);
        }
        input.value = '';
    });
}

/**
 * Durum alanına ilerleme / mesaj yazar. state: uploading | processing | success | error
 */
export function renderStatus(element, state, message, percent = null) {
    element.replaceChildren();
    const wrapper = document.createElement('div');
    wrapper.className = 'small d-flex flex-column gap-2';

    const text = document.createElement('div');
    text.className = state === 'error' ? 'text-danger' : (state === 'success' ? 'text-success' : 'text-body-secondary');
    if (state === 'uploading' || state === 'processing') {
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-2';
        spinner.setAttribute('aria-hidden', 'true');
        text.append(spinner);
    }
    text.append(document.createTextNode(message));
    wrapper.append(text);

    if (percent !== null) {
        const progress = document.createElement('progress');
        progress.className = 'upload-progress w-100';
        progress.max = 100;
        progress.value = percent;
        progress.setAttribute('aria-label', message);
        wrapper.append(progress);
    }
    element.append(wrapper);
}
