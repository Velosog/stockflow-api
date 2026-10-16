<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

final class IssueApiToken
{
    /**
     * bcrypt hash (cost 12) of random bytes that were thrown away. Checking a
     * password against it when the email is unknown keeps the response time
     * the same as for a wrong password, so timing does not reveal which
     * emails have accounts.
     */
    private const string TIMING_SAFE_DUMMY_HASH = '$2y$12$ve/yQpXmZPNvEHIi7OGlmOPKr4O8GftqgUeuPoa19jaoaI6XGjTqW';

    public function handle(string $email, string $password, string $deviceName): NewAccessToken
    {
        $user = User::query()->where('email', mb_strtolower($email))->first();

        $passwordMatches = Hash::check($password, $user->password ?? self::TIMING_SAFE_DUMMY_HASH);

        if ($user === null || ! $passwordMatches) {
            // Same message for "unknown email" and "wrong password".
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $minutes = Config::integer('sanctum.expiration');

        return $user->createToken($deviceName, ['*'], $minutes > 0 ? now()->addMinutes($minutes) : null);
    }
}
