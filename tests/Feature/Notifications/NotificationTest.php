<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\LowStockNotification;

function lowStockNotification(): LowStockNotification
{
    return new LowStockNotification(1, 'HB-M8-100', 'Hex bolt', 1, 'WH-NORTH', 3, 10);
}

it('lists only the authenticated user notifications and filters unread', function (): void {
    $manager = actingAsRole(Role::Manager);
    $manager->notify(lowStockNotification());
    $manager->notify(lowStockNotification());
    $manager->notifications()->first()?->markAsRead();
    User::factory()->manager()->create()->notify(lowStockNotification());

    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/notifications?unread=true')->assertOk()->assertJsonCount(1, 'data');
});

it('marks a notification as read', function (): void {
    $manager = actingAsRole(Role::Manager);
    $manager->notify(lowStockNotification());
    $id = $manager->notifications()->value('id');

    $this->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.read_at', fn (?string $readAt) => $readAt !== null);
});

it('answers 404 for another user notification without revealing it exists', function (): void {
    $other = User::factory()->manager()->create();
    $other->notify(lowStockNotification());
    $id = $other->notifications()->value('id');
    actingAsRole(Role::Manager);

    assertProblem($this->postJson("/api/v1/notifications/{$id}/read"), 404, 'not_found');
    expect($other->notifications()->first()?->read_at)->toBeNull();
});

it('requires authentication', function (): void {
    assertProblem($this->getJson('/api/v1/notifications'), 401, 'unauthenticated');
});
