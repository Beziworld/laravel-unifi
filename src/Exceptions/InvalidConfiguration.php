<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

/**
 * Raised when a connection is not configured or incomplete.
 */
final class InvalidConfiguration extends UnifiException
{
    public static function missing(string $key, string $env, string $connection = 'default'): self
    {
        return new self(sprintf('UniFi connection "%s" has no "%s" configured. Set %s in the environment or in the connection array.', $connection, $key, $env));
    }

    public static function unknownConnection(string $connection): self
    {
        return new self(sprintf('UniFi connection "%s" is not defined in config/unifi.php.', $connection));
    }

    public static function invalid(string $key, string $value, string $connection = 'default'): self
    {
        return new self(sprintf('UniFi connection "%s" has an invalid "%s" value "%s".', $connection, $key, $value));
    }

    public static function apiKeyRequired(string $connection): self
    {
        return new self(sprintf('UniFi connection "%s" has no API key; the official Network API requires one (UNIFI_API_KEY).', $connection));
    }

    public static function credentialsRequired(string $connection): self
    {
        return new self(sprintf('UniFi connection "%s" has no username and password; the classic controller API requires them (UNIFI_USERNAME, UNIFI_PASSWORD).', $connection));
    }
}
