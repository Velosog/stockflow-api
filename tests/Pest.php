<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the full application and run against PostgreSQL inside a
| transaction that is rolled back after each test (RefreshDatabase).
| Unit tests also extend the Laravel TestCase because actions use the container.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
