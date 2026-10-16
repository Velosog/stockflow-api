<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error as RFC 9457 "problem details":
 *
 *     { "title": "Conflict", "status": 409, "code": "insufficient_stock",
 *       "detail": "...", "request_id": "..." }
 *
 * Messages are written for API clients and never expose internals (class
 * names, SQL, stack traces), even when APP_DEBUG is on.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->problem(
                $request, 422, 'validation_failed', $e->getMessage(), ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => $this->problem(
                $request, 401, 'unauthenticated', 'A valid bearer token is required.',
            ),
            $e instanceof DomainRuleException => $this->problem(
                $request, $e->status(), $e->errorCode(), $e->getMessage(), $e->context(),
            ),
            $e instanceof AccessDeniedHttpException => $this->problem(
                $request, 403, 'forbidden', $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
            ),
            $e instanceof NotFoundHttpException => $this->problem(
                // The default message names the Eloquent model; keep it generic.
                $request, 404, 'not_found', 'The requested resource was not found.',
            ),
            $e instanceof HttpExceptionInterface => $this->problem(
                $request,
                $e->getStatusCode(),
                $this->codeForStatus($e->getStatusCode()),
                $e->getStatusCode() === 429
                    ? 'Too many requests. Retry after the number of seconds in the Retry-After header.'
                    : ($e->getMessage() !== '' ? $e->getMessage() : $this->titleFor($e->getStatusCode())),
                headers: $e->getHeaders(),
            ),
            default => $this->problem(
                $request, 500, 'server_error', 'An unexpected error occurred. Use the request_id when reporting it.',
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string|list<string>>  $headers
     */
    private function problem(
        Request $request,
        int $status,
        string $code,
        string $detail,
        array $extra = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'title' => $this->titleFor($status),
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            ...$extra,
            'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
        ];

        return new JsonResponse($body, $status, [
            ...$headers,
            'Content-Type' => 'application/problem+json',
        ]);
    }

    private function titleFor(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Error';
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            405 => 'method_not_allowed',
            409 => 'conflict',
            415 => 'unsupported_media_type',
            429 => 'too_many_requests',
            503 => 'service_unavailable',
            default => 'http_'.$status,
        };
    }
}
