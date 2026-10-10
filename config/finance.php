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
    | Secret required to approve credit-limit overrides and discounts/below-retail
    | prices at POS. Prefer MANAGER_OVERRIDE_CODE_HASH (output of Hash::make);
    | MANAGER_OVERRIDE_CODE is a plain secret compared in constant time.
    | There is no default and no fallback: if neither is set, overrides are refused.
    |
    */
    'manager_override_code' => env('MANAGER_OVERRIDE_CODE', null),
    'manager_override_hash' => env('MANAGER_OVERRIDE_CODE_HASH', null),
];
