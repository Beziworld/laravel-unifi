<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Conversions of loosely typed API values into strict PHP types.
 *
 * @internal
 */
final class Values
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Interprets booleans, 1/0 and "true"/"false".
     *
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * Parses an ISO 8601 timestamp of the official API into UTC.
     *
     * @param array<array-key, mixed> $data
     */
    public static function date(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parses a Unix timestamp (seconds or milliseconds) of the classic API into UTC.
     *
     * @param array<array-key, mixed> $data
     */
    public static function timestamp(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        $seconds = (int) $value;

        if ($seconds > 100_000_000_000) {
            $seconds = intdiv($seconds, 1000);
        }

        return CarbonImmutable::createFromTimestampUTC($seconds);
    }

    /**
     * Returns the list items of a value that are arrays.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }
}
