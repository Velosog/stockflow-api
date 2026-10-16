<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $request_fingerprint
 * @property int|null $response_status
 * @property string|null $response_body
 * @property Carbon $expires_at
 */
#[Fillable(['user_id', 'key', 'request_fingerprint', 'response_status', 'response_body', 'expires_at'])]
class IdempotencyKey extends Model
{
    use Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Expired keys are deleted by the scheduled `model:prune` command.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }
}
