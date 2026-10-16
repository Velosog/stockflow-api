<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Removes expired rows from Prunable models (e.g. idempotency keys).
Schedule::command('model:prune')->daily();

// Deletes Sanctum tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
