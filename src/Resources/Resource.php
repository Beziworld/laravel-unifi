<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Http\IntegrationApi;
use Beziworld\Unifi\Support\Filter;
use Closure;
use Illuminate\Support\LazyCollection;

/**
 * Base class of the official API areas scoped to a site.
 */
abstract class Resource
{
    /**
     * @param Closure(): string $siteId Resolves the site UUID lazily, so that site lookups happen only when needed.
     */
    public function __construct(
        protected readonly IntegrationApi $api,
        private readonly Closure $siteId,
    ) {}

    protected function siteId(): string
    {
        return ($this->siteId)();
    }

    protected function sitePath(string $path = ''): string
    {
        return '/v1/sites/' . rawurlencode($this->siteId()) . $path;
    }

    protected static function segment(string $id): string
    {
        return '/' . rawurlencode($id);
    }

    /**
     * @template TItem
     *
     * @param Closure(array<array-key, mixed>): TItem $map
     * @return Page<TItem>
     */
    protected function page(string $path, Closure $map, Filter|string|null $filter, int $offset, int $limit): Page
    {
        return Page::fromArray($this->api->get($path, [
            'offset' => $offset,
            'limit' => $limit,
            'filter' => $filter === null ? null : (string) $filter,
        ]), $map);
    }

    /**
     * Iterates over every item of a list endpoint, requesting further pages on demand.
     *
     * @template TItem
     *
     * @param Closure(array<array-key, mixed>): TItem $map
     * @return LazyCollection<int, TItem>
     */
    protected function cursor(string $path, Closure $map, Filter|string|null $filter, int $pageSize): LazyCollection
    {
        return LazyCollection::make(function () use ($path, $map, $filter, $pageSize) {
            $offset = 0;

            do {
                $page = $this->page($path, $map, $filter, $offset, $pageSize);

                foreach ($page->items as $item) {
                    yield $item;
                }

                $offset = $page->nextOffset();
            } while ($page->hasMore());
        });
    }
}
