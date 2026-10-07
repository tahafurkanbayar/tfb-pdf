<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // İmza davet bağlantısının geçerlilik süresi (gün)
    'invite_ttl_days' => Env::int('SIGNATURE_INVITE_DAYS', 14),
];
