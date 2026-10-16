<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('limits mutating requests per user', function (): void {
    config(['stockflow.rate_limits.writes' => 2]);
    actingAsRole(Role::Manager);
    $product = Product::factory()->create();

    $this->patchJson("/api/v1/products/{$product->id}", ['name' => 'One'])->assertOk();
    $this->patchJson("/api/v1/products/{$product->id}", ['name' => 'Two'])->assertOk();

    assertProblem($this->patchJson("/api/v1/products/{$product->id}", ['name' => 'Three']), 429, 'too_many_requests')
        ->assertHeader('Retry-After');

    // Reads use the separate, wider "api" limit.
    $this->getJson("/api/v1/products/{$product->id}")->assertOk()->assertJsonPath('data.name', 'Two');
});

it('limits all authenticated traffic per user', function (): void {
    config(['stockflow.rate_limits.api' => 3]);
    actingAsRole(Role::Operator);

    foreach (range(1, 3) as $_) {
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    assertProblem($this->getJson('/api/v1/auth/me'), 429, 'too_many_requests');
});

it('keeps limits separate for each user', function (): void {
    config(['stockflow.rate_limits.api' => 1]);

    actingAsRole(Role::Operator);
    $this->getJson('/api/v1/auth/me')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertTooManyRequests();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/auth/me')->assertOk();
});
