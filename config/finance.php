<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance Precision
    |--------------------------------------------------------------------------
    |
    | Epsilon used for comparing monetary amounts (halala tolerance).
    | All amounts are DECIMAL(10,2) and must be compared with this epsilon.
    |
    */
    'epsilon' => env('FINANCE_EPSILON', 0.01),

    /*
    |--------------------------------------------------------------------------
    | Manager Override
    |--------------------------------------------------------------------------
    |
    | Hashed manager override code. Use env MANAGER_OVERRIDE_CODE_HASH
    | or plain code MANAGER_OVERRIDE_CODE (will be hashed on first use).
    | No default fallback — must be explicitly configured.
    |
    */
    'manager_override_code' => env('MANAGER_OVERRIDE_CODE', null),
    'manager_override_hash' => env('MANAGER_OVERRIDE_CODE_HASH', null),
];
