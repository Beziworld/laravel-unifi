<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

use Throwable;

/**
 * Raised when the controller cannot be reached (DNS, TCP, TLS or timeout errors).
 */
final class ConnectionFailed extends UnifiException
{
    public static function during(string $operation, string $url, Throwable $previous): self
    {
        return new self(sprintf('Could not reach the UniFi controller at %s for %s: %s', $url, $operation, $previous->getMessage()), 0, $previous);
    }
}
