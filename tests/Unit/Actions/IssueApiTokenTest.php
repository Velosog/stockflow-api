<?php

declare(strict_types=1);

use App\Actions\Auth\IssueApiToken;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->password = strongPassword();
    User::factory()->create(['email' => 'manager@stockflow.test', 'password' => $this->password]);
});

it('issues a token that expires after the configured number of minutes', function (): void {
    config(['sanctum.expiration' => 60]);
    $this->freezeSecond();

    $token = app(IssueApiToken::class)->handle('manager@stockflow.test', $this->password, 'cli');

    expect($token->accessToken->expires_at?->equalTo(now()->addMinutes(60)))->toBeTrue()
        ->and($token->accessToken->name)->toBe('cli');
});

it('fails the same way for an unknown email', function (): void {
    expect(fn () => app(IssueApiToken::class)->handle('ghost@stockflow.test', $this->password, 'cli'))
        ->toThrow(ValidationException::class, 'These credentials do not match our records.');
});

it('fails the same way for a wrong password', function (): void {
    expect(fn () => app(IssueApiToken::class)->handle('manager@stockflow.test', 'x'.$this->password, 'cli'))
        ->toThrow(ValidationException::class, 'These credentials do not match our records.');
});
