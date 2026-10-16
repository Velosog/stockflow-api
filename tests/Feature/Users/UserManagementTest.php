<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lets an admin list users and filter by role', function (): void {
    actingAsRole(Role::Admin);
    User::factory()->manager()->count(2)->create();
    User::factory()->operator()->count(3)->create();

    $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);

    $this->getJson('/api/v1/users?role=manager')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets an admin create a user with a hashed password', function (): void {
    actingAsRole(Role::Admin);
    $password = strongPassword();

    $this->postJson('/api/v1/users', [
        'name' => 'New Operator',
        'email' => ' New.Operator@StockFlow.test ',
        'password' => $password,
        'role' => 'operator',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new.operator@stockflow.test')
        ->assertJsonPath('data.role', 'operator')
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('email', 'new.operator@stockflow.test')->sole();
    expect($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

it('enforces the password policy and unique emails', function (): void {
    actingAsRole(Role::Admin);
    User::factory()->create(['email' => 'taken@stockflow.test']);

    assertProblem($this->postJson('/api/v1/users', [
        'name' => 'Weak',
        'email' => 'TAKEN@stockflow.test',
        'password' => 'short',
        'role' => 'superuser',
    ]), 422, 'validation_failed')
        ->assertJsonValidationErrors(['email', 'password', 'role']);
});

it('forbids non-admins from managing users', function (Role $role): void {
    actingAsRole($role);

    assertProblem($this->getJson('/api/v1/users'), 403, 'forbidden');
    assertProblem($this->postJson('/api/v1/users', []), 403, 'forbidden');
})->with([Role::Manager, Role::Operator]);

it('requires authentication to manage users', function (): void {
    assertProblem($this->getJson('/api/v1/users'), 401, 'unauthenticated');
    assertProblem($this->postJson('/api/v1/users', []), 401, 'unauthenticated');
});
