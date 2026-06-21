<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limiting для формы обратной связи
    |--------------------------------------------------------------------------
    |
    | Лимитер хранит состояние в файловом кеше (CACHE_STORE=file).
    |
    */

    'rate_limit' => [
        'max_attempts' => env('CONTACT_RATE_LIMIT_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('CONTACT_RATE_LIMIT_DECAY_MINUTES', 1),
    ],

];
