<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

use Throwable;

/**
 * Raised when the controller rejects a request or answers with an error envelope.
 *
 * The error code is the machine readable code of the official API (for
 * example "api.authentication.missing-credentials") or the "meta.msg" value
 * of the classic API (for example "api.err.InvalidPayload").
 */
class ApiException extends UnifiException
{
    /**
     * @param array<array-key, mixed> $payload
     */
    final public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly ?int $httpStatus = null,
        private readonly ?string $requestId = null,
        private readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * Builds the exception from an error body of the official or the classic API.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(string $operation, int $httpStatus, array $payload): static
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        $code = self::text($payload['code'] ?? null) ?? self::text($meta['msg'] ?? null) ?? self::text($payload['statusName'] ?? null);
        $message = self::text($payload['message'] ?? null) ?? self::text($meta['msg'] ?? null) ?? ('HTTP ' . $httpStatus);
        $requestId = self::text($payload['requestId'] ?? null) ?? self::text($payload['traceId'] ?? null);

        $text = sprintf('UniFi %s failed: %s', $operation, $message);

        if ($code !== null && $code !== $message) {
            $text .= ' [' . $code . ']';
        }

        if ($httpStatus > 0) {
            $text .= sprintf(' (HTTP %d)', $httpStatus);
        }

        return new static($text, $code, $httpStatus > 0 ? $httpStatus : null, $requestId, $payload);
    }

    public static function unexpected(string $operation, int $httpStatus): static
    {
        return new static(sprintf('UniFi %s returned an unexpected response (HTTP %d).', $operation, $httpStatus), null, $httpStatus);
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Request identifier reported by the controller or the cloud connector, useful for support cases.
     */
    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
