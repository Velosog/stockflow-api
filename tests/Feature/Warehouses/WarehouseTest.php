<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Warehouse;

it('lets every role list and show warehouses', function (Role $role): void {
    actingAsRole($role);
    $warehouse = Warehouse::factory()->create(['city' => 'Riverton']);
    Warehouse::factory()->create(['city' => 'Lakeside']);

    $this->getJson('/api/v1/warehouses')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/warehouses?city=riverton')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/warehouses/{$warehouse->id}")->assertOk()->assertJsonPath('data.code', $warehouse->code);
})->with([Role::Admin, Role::Manager, Role::Operator]);

it('lets an admin create, update and delete a warehouse', function (): void {
    actingAsRole(Role::Admin);

    $id = $this->postJson('/api/v1/warehouses', ['code' => 'wh-west', 'name' => 'West Hub', 'city' => 'Pinewood'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'WH-WEST')
        ->json('data.id');

    $this->patchJson("/api/v1/warehouses/{$id}", ['name' => 'West Hub II'])
        ->assertOk()
        ->assertJsonPath('data.name', 'West Hub II');

    $this->deleteJson("/api/v1/warehouses/{$id}")->assertNoContent();
    $this->assertDatabaseMissing('warehouses', ['id' => $id]);
});

it('rejects duplicate warehouse codes', function (): void {
    actingAsRole(Role::Admin);
    Warehouse::factory()->create(['code' => 'WH-WEST']);

    assertProblem($this->postJson('/api/v1/warehouses', ['code' => 'wh-west', 'name' => 'x', 'city' => 'y']), 422, 'validation_failed')
        ->assertJsonValidationErrors(['code']);
});

it('forbids non-admins from managing warehouses', function (Role $role): void {
    actingAsRole($role);
    $warehouse = Warehouse::factory()->create();

    assertProblem($this->postJson('/api/v1/warehouses', ['code' => 'WH-X1', 'name' => 'x', 'city' => 'y']), 403, 'forbidden');
    assertProblem($this->patchJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'x']), 403, 'forbidden');
    assertProblem($this->deleteJson("/api/v1/warehouses/{$warehouse->id}"), 403, 'forbidden');
})->with([Role::Manager, Role::Operator]);

it('requires authentication', function (): void {
    assertProblem($this->getJson('/api/v1/warehouses'), 401, 'unauthenticated');
});
