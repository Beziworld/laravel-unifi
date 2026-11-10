<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Closure;

/**
 * One page of a list endpoint of the official Network API.
 *
 * @template TItem
 */
final readonly class Page
{
    /**
     * @param list<TItem> $items
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public array $items,
        public int $offset,
        public int $limit,
        public int $count,
        public int $totalCount,
        public array $raw = [],
    ) {}

    /**
     * @template TMapped
     *
     * @param array<array-key, mixed> $payload
     * @param Closure(array<array-key, mixed>): TMapped $map
     * @return self<TMapped>
     */
    public static function fromArray(array $payload, Closure $map): self
    {
        $items = array_map($map, Values::rows($payload['data'] ?? []));

        return new self(
            items: $items,
            offset: Values::int($payload, 'offset'),
            limit: Values::int($payload, 'limit', count($items)),
            count: Values::int($payload, 'count', count($items)),
            totalCount: Values::int($payload, 'totalCount', count($items)),
            raw: $payload,
        );
    }

    public function hasMore(): bool
    {
        return $this->count > 0 && $this->offset + $this->count < $this->totalCount;
    }

    public function nextOffset(): int
    {
        return $this->offset + $this->count;
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }
}
