<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Raised when the controller or the cloud connector answers with HTTP 429.
 */
final class RateLimitException extends ApiException
{
    private ?int $retryAfter = null;

    public static function fromResponse(string $operation, Response $response): self
    {
        $payload = $response->json();
        $exception = self::fromPayload($operation, 429, is_array($payload) ? $payload : []);

        $header = trim($response->header('Retry-After'));

        if ($header !== '') {
            $exception->retryAfter = is_numeric($header) ? max(0, (int) $header) : self::secondsUntil($header);
        }

        return $exception;
    }

    /**
     * Seconds to wait before the next request, when the server sent a Retry-After header.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    private static function secondsUntil(string $date): ?int
    {
        $timestamp = strtotime($date);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }
}
