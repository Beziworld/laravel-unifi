<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Data\Site;
use Beziworld\Unifi\Support\Filter;
use Illuminate\Support\LazyCollection;

/**
 * Sites of the Network application (GET /v1/sites).
 */
final class Sites extends Resource
{
    /**
     * @param Filter|string|null $filter Filterable: id, internalReference, name.
     * @return Page<Site>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page('/v1/sites', Site::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, Site>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor('/v1/sites', Site::fromArray(...), $filter, 200);
    }

    /**
     * Finds a site by its internal reference (the classic short name, e.g. "default").
     */
    public function findByReference(string $reference): ?Site
    {
        return $this->list(Filter::eq('internalReference', $reference), 0, 1)->first();
    }

    public function find(string $siteId): ?Site
    {
        return $this->list(Filter::eq('id', $siteId), 0, 1)->first();
    }
}
