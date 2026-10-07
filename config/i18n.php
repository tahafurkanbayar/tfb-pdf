<?php

declare(strict_types=1);

return [
    // Desteklenen diller (URL öneki olarak da kullanılır: /tr/, /en/)
    'locales' => [
        'tr' => 'Türkçe',
        'en' => 'English',
    ],
    // Tercih yoksa ve tarayıcı dili desteklenmiyorsa
    'default' => 'tr',
    'cookie' => 'tfb_locale',
];
