<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a POST safe to retry. With an "Idempotency-Key" header:
 *
 * - first request: the key is claimed atomically, the request runs, and a
 *   successful (2xx) response is stored;
 * - retry with the same key and body: the stored response is replayed with
 *   "Idempotent-Replayed: true" and nothing runs again;
 * - same key, different body: 422; same key while the first is running: 409.
 *
 * Failed responses are not stored, so the client may retry with the same key
 * (for example after stock is replenished). Keys are scoped per user.
 */
final class EnsureIdempotentRequest
{
    public const string HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);

        if ($key === null) {
            return $next($request);
        }

        if (preg_match('/\A[\x21-\x7E]{1,255}\z/', $key) !== 1) {
            throw IdempotencyException::invalidKey();
        }

        $userId = $request->user()?->getAuthIdentifier();
        $fingerprint = $this->fingerprint($request);

        if (! $this->claim($userId, $key, $fingerprint)) {
            return $this->replay($userId, $key, $fingerprint);
        }

        $response = $next($request);

        if ($response->isSuccessful() && $response instanceof JsonResponse) {
            IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        } else {
            // Release the key: nothing was created, so a retry must run again.
            IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->delete();
        }

        return $response;
    }

    /**
     * Atomically insert the key. Returns false if it already exists.
     */
    private function claim(mixed $userId, string $key, string $fingerprint): bool
    {
        // An expired key may be reused (the prune job may not have run yet).
        IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->where('expires_at', '<=', now())
            ->delete();

        return IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $userId,
            'key' => $key,
            'request_fingerprint' => $fingerprint,
            'expires_at' => now()->addHours(Config::integer('stockflow.idempotency.ttl_hours')),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function replay(mixed $userId, string $key, string $fingerprint): Response
    {
        $stored = IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->first();

        // Deleted in between by a failed first attempt: ask the client to retry.
        if ($stored === null || ! $stored->isCompleted()) {
            throw IdempotencyException::stillProcessing();
        }

        if (! hash_equals($stored->request_fingerprint, $fingerprint)) {
            throw IdempotencyException::reusedWithDifferentPayload();
        }

        return new Response((string) $stored->response_body, (int) $stored->response_status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    /**
     * SHA-256 of method, path and the JSON body with keys sorted, so that
     * key order or whitespace differences do not count as a different body.
     */
    private function fingerprint(Request $request): string
    {
        $body = $request->json()->all();
        $this->sortKeysRecursively($body);

        return hash('sha256', $request->method().' '.$request->path()."\n".json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function sortKeysRecursively(array &$data): void
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->sortKeysRecursively($value);
            }
        }
    }
}
