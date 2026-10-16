<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | StockFlow is a token-only API (no first-party SPA using cookies), so no
    | domain receives stateful cookie authentication.
    |
    */

    'stateful' => [],

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | No session guard is checked: every request must carry a bearer token.
    |
    */

    'guard' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Tokens expire after this many minutes. Expired tokens are rejected and
    | removed by the scheduled `sanctum:prune-expired` command.
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 1440),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | A recognisable prefix lets secret scanners (GitHub, gitleaks) detect
    | tokens that are accidentally committed.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sf_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
