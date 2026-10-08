// Tema başlatma: <head> içinde, CSS'ten önce ve engelleyici (defer/module olmadan) yüklenir; böylece sayfa
// ilk çizimden önce doğru temayla açılır, beyaz flaş olmaz. CSP inline script'e izin vermediği için ayrı dosyadır.
// Tercih: localStorage "tfb-theme" = light | dark | auto (yoksa auto → prefers-color-scheme).
(function () {
    'use strict';

    var KEY = 'tfb-theme';
    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function stored() {
        try {
            var value = window.localStorage.getItem(KEY);
            return value === 'light' || value === 'dark' || value === 'auto' ? value : 'auto';
        } catch (e) {
            // Gizli pencere / engellenmiş depolama: sistem tercihine uy
            return 'auto';
        }
    }

    function resolve(preference) {
        if (preference === 'auto') {
            return media && media.matches ? 'dark' : 'light';
        }
        return preference;
    }

    function apply(preference) {
        root.setAttribute('data-bs-theme', resolve(preference));
        root.setAttribute('data-theme-preference', preference);
    }

    apply(stored());

    // Sistem teması değişirse (yalnızca "auto" seçiliyken) anında uygula
    if (media) {
        var onChange = function () {
            if (stored() === 'auto') {
                apply('auto');
            }
        };
        if (media.addEventListener) {
            media.addEventListener('change', onChange);
        } else if (media.addListener) {
            media.addListener(onChange);
        }
    }

    // app.js tema seçicisi bu işlevleri kullanır
    window.tfbTheme = {
        get: stored,
        set: function (preference) {
            try {
                window.localStorage.setItem(KEY, preference);
            } catch (e) {
                // Kaydedilemese de bu sayfada uygulanır
            }
            apply(preference);
        },
        resolve: resolve,
    };
}());
