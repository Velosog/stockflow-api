<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    $this->password = strongPassword();
    $this->user = User::factory()->create([
        'email' => 'operator@stockflow.test',
        'password' => $this->password,
        'role' => Role::Operator,
    ]);
});

function credentials(string $password, array $overrides = []): array
{
    return [
        'email' => 'operator@stockflow.test',
        'password' => $password,
        'device_name' => 'pest',
        ...$overrides,
    ];
}

it('issues a prefixed, expiring token for valid credentials', function (): void {
    $response = $this->postJson('/api/v1/auth/login', credentials($this->password))
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'email', 'role']]])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.role', 'operator');

    // Sanctum format: "<token id>|<prefix><random><crc32 checksum>".
    $plainToken = $response->json('data.token');
    expect($plainToken)->toMatch('/\A\d+\|sf_[A-Za-z0-9]{40}[a-f0-9]{8}\z/')
        ->and($response->json('data.expires_at'))->not->toBeNull();

    // Only a hash is stored, never the token itself.
    $stored = PersonalAccessToken::query()->sole();
    expect($stored->token)->not->toBe($plainToken)
        ->and($stored->name)->toBe('pest');
});

it('treats the email case-insensitively', function (): void {
    $this->postJson('/api/v1/auth/login', credentials($this->password, ['email' => 'Operator@StockFlow.test']))->assertOk();
});

it('rejects a wrong password and an unknown email with the same message', function (array $payload): void {
    assertProblem($this->postJson('/api/v1/auth/login', credentials($this->password, $payload)), 422, 'validation_failed')
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
})->with([
    'wrong password' => [['password' => 'definitely-not-it']],
    'unknown email' => [['email' => 'nobody@stockflow.test']],
]);

it('validates the login payload', function (): void {
    assertProblem($this->postJson('/api/v1/auth/login', []), 422, 'validation_failed')
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('throttles repeated login attempts', function (): void {
    config(['stockflow.rate_limits.login' => 2]);

    $this->postJson('/api/v1/auth/login', credentials('nope'))->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', credentials('nope'))->assertUnprocessable();

    assertProblem($this->postJson('/api/v1/auth/login', credentials($this->password)), 429, 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('returns the authenticated user', function (): void {
    $token = $this->user->createToken('pest')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'operator@stockflow.test')
        ->assertJsonMissingPath('data.password');
});

it('rejects requests without a token', function (): void {
    assertProblem($this->getJson('/api/v1/auth/me'), 401, 'unauthenticated');
});

it('answers with JSON even when the client does not ask for it', function (): void {
    assertProblem($this->get('/api/v1/auth/me'), 401, 'unauthenticated');
});

it('rejects an expired token', function (): void {
    $token = $this->user->createToken('pest', ['*'], now()->subMinute())->plainTextToken;

    assertProblem($this->withToken($token)->getJson('/api/v1/auth/me'), 401, 'unauthenticated');
});

it('revokes the current token on logout', function (): void {
    $token = $this->user->createToken('pest')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect(PersonalAccessToken::query()->count())->toBe(0);

    // The guard caches the user between requests inside one test; reset it.
    $this->app['auth']->forgetGuards();

    assertProblem($this->withToken($token)->getJson('/api/v1/auth/me'), 401, 'unauthenticated');
});
