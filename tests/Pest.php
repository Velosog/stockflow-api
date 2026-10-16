<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
| Feature tests boot the full application and run against PostgreSQL inside a
| transaction that is rolled back after each test (RefreshDatabase).
| Unit tests also extend the Laravel TestCase because actions use the container.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * A random password that satisfies the password policy. Generated at runtime
 * so no credential-looking literal is ever committed (gitleaks stays strict).
 */
function strongPassword(): string
{
    return 'Aa1-'.Str::password(16);
}

/**
 * Create a user with the given role and authenticate as them via Sanctum.
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->create(['role' => $role]);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Assert the response is an RFC 9457 problem details body with our extensions.
 *
 * @param  TestResponse<Symfony\Component\HttpFoundation\Response>  $response
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function assertProblem(TestResponse $response, int $status, string $code): TestResponse
{
    $response->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', $status)
        ->assertJsonPath('code', $code)
        ->assertJsonStructure(['title', 'status', 'code', 'detail', 'request_id']);

    return $response;
}
