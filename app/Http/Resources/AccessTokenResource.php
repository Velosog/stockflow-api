<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * The only response that ever contains a plain-text token. It is shown once;
 * the database stores only a SHA-256 hash of it.
 *
 * @property NewAccessToken $resource
 */
final class AccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($this->resource->accessToken->tokenable),
        ];
    }
}
