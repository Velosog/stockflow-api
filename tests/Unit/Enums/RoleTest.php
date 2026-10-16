<?php

declare(strict_types=1);

use App\Enums\Role;

/*
| The whole permission matrix in one table: [role, ability, allowed].
*/

it('implements the permission matrix', function (Role $role, string $ability, bool $allowed): void {
    expect($role->{$ability}())->toBe($allowed);
})->with([
    [Role::Admin, 'canManageUsers', true],
    [Role::Manager, 'canManageUsers', false],
    [Role::Operator, 'canManageUsers', false],

    [Role::Admin, 'canManageWarehouses', true],
    [Role::Manager, 'canManageWarehouses', false],
    [Role::Operator, 'canManageWarehouses', false],

    [Role::Admin, 'canManageCatalog', true],
    [Role::Manager, 'canManageCatalog', true],
    [Role::Operator, 'canManageCatalog', false],

    [Role::Admin, 'canAdjustStock', true],
    [Role::Manager, 'canAdjustStock', true],
    [Role::Operator, 'canAdjustStock', false],

    [Role::Admin, 'canApproveOrders', true],
    [Role::Manager, 'canApproveOrders', true],
    [Role::Operator, 'canApproveOrders', false],

    [Role::Admin, 'receivesLowStockAlerts', false],
    [Role::Manager, 'receivesLowStockAlerts', true],
    [Role::Operator, 'receivesLowStockAlerts', false],
]);
