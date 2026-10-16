<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | "login" is applied per email + IP (with a wider per-IP ceiling), "api" to
    | every authenticated request and "writes" additionally to mutating routes.
    |
    */

    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
        'api' => (int) env('RATE_LIMIT_API_PER_MINUTE', 120),
        'writes' => (int) env('RATE_LIMIT_WRITES_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency keys (POST /api/v1/orders)
    |--------------------------------------------------------------------------
    */

    'idempotency' => [
        'ttl_hours' => (int) env('IDEMPOTENCY_KEY_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
    ],

];
