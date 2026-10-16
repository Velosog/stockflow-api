<?php

declare(strict_types=1);

use App\Enums\Role;

it('answers unknown API routes with a problem details 404', function (): void {
    assertProblem($this->getJson('/api/v1/does-not-exist'), 404, 'not_found');
});

it('answers unsupported methods with 405', function (): void {
    actingAsRole(Role::Operator);

    assertProblem($this->putJson('/api/v1/auth/me'), 405, 'method_not_allowed');
});

it('assigns a request id and returns it in the header and error body', function (): void {
    $response = $this->getJson('/api/v1/does-not-exist');

    $requestId = $response->headers->get('X-Request-Id');
    expect($requestId)->toBeString()->not->toBeEmpty()
        ->and($response->json('request_id'))->toBe($requestId);
});

it('reuses a well-formed client request id but replaces a malformed one', function (): void {
    $this->getJson('/up', ['X-Request-Id' => 'client-trace-1234'])
        ->assertHeader('X-Request-Id', 'client-trace-1234');

    $replaced = $this->getJson('/up', ['X-Request-Id' => "bad\nvalue<script>"])->headers->get('X-Request-Id');
    expect($replaced)->not->toContain('script');
});

it('sends security headers on API responses', function (): void {
    actingAsRole(Role::Operator);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});
