<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Support;

use DateTimeInterface;
use Stringable;

/**
 * Builder of the "filter" query parameter of the official Network API.
 *
 * Produces property expressions ("name.eq('Lobby')"), compound expressions
 * ("and(a, b)", "or(a, b)") and negations ("not(a)"). String values are
 * wrapped in single quotes with embedded quotes doubled, numbers and UUIDs
 * are written as they are, dates as ISO 8601 in UTC.
 */
final class Filter implements Stringable
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private function __construct(
        private readonly string $expression,
    ) {}

    public static function raw(string $expression): self
    {
        return new self($expression);
    }

    public static function eq(string $property, mixed $value): self
    {
        return self::call($property, 'eq', [$value]);
    }

    public static function ne(string $property, mixed $value): self
    {
        return self::call($property, 'ne', [$value]);
    }

    public static function gt(string $property, mixed $value): self
    {
        return self::call($property, 'gt', [$value]);
    }

    public static function ge(string $property, mixed $value): self
    {
        return self::call($property, 'ge', [$value]);
    }

    public static function lt(string $property, mixed $value): self
    {
        return self::call($property, 'lt', [$value]);
    }

    public static function le(string $property, mixed $value): self
    {
        return self::call($property, 'le', [$value]);
    }

    /**
     * Pattern match; "*" matches any sequence of characters.
     */
    public static function like(string $property, string $pattern): self
    {
        return self::call($property, 'like', [$pattern]);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function in(string $property, iterable $values): self
    {
        return self::call($property, 'in', array_values([...$values]));
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function notIn(string $property, iterable $values): self
    {
        return self::call($property, 'notIn', array_values([...$values]));
    }

    public static function isNull(string $property): self
    {
        return self::call($property, 'isNull', []);
    }

    public static function isNotNull(string $property): self
    {
        return self::call($property, 'isNotNull', []);
    }

    public static function and(self|string ...$expressions): self
    {
        return new self('and(' . implode(', ', array_map(strval(...), $expressions)) . ')');
    }

    public static function or(self|string ...$expressions): self
    {
        return new self('or(' . implode(', ', array_map(strval(...), $expressions)) . ')');
    }

    public static function not(self|string $expression): self
    {
        return new self('not(' . $expression . ')');
    }

    /**
     * Formats a single argument according to the filter syntax.
     */
    public static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            is_string($value) && preg_match(self::UUID, $value) === 1 => strtolower($value),
            is_array($value) => '[' . implode(', ', array_map(self::literal(...), $value)) . ']',
            default => "'" . str_replace("'", "''", is_scalar($value) || $value instanceof Stringable ? (string) $value : '') . "'",
        };
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function call(string $property, string $function, array $arguments): self
    {
        return new self(sprintf('%s.%s(%s)', $property, $function, implode(', ', array_map(self::literal(...), $arguments))));
    }
}
