<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

it('generates an OpenAPI 3.1 document for every v1 endpoint, secured with bearer tokens', function (): void {
    Gate::define('viewApiDocs', fn ($user = null) => true);

    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['openapi'])->toStartWith('3.1')
        ->and($spec['info']['version'])->toBe('0.1.0')
        ->and(array_keys($spec['paths']))->toContain(
            '/v1/auth/login',
            '/v1/products',
            '/v1/orders',
            '/v1/orders/{order}/confirm',
            '/v1/stock-movements',
            '/v1/notifications',
        )
        ->and($spec['components']['securitySchemes'])->toHaveKey('http')
        ->and($spec['paths']['/v1/auth/login']['post'])->toHaveKey('security');
});

it('does not serve the docs outside the local environment by default', function (): void {
    $this->get('/docs/api.json')->assertForbidden();
});
