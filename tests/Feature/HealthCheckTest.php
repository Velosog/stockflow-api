<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('reports the application as healthy', function (): void {
    $this->get('/up')->assertOk();
});

it('runs the test suite against PostgreSQL', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});
