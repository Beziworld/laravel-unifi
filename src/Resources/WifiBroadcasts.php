<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Data\WifiBroadcast;
use Beziworld\Unifi\Support\Filter;
use Illuminate\Support\LazyCollection;

/**
 * Wi-Fi broadcasts (SSIDs / WLANs) of a site.
 *
 * Create and update bodies follow the "Wifi broadcast create or update"
 * schema (type, name, enabled, network, securityConfiguration, ...).
 * The official update is a full replacement, so enable() and disable()
 * read the current definition and send it back with the changed flag.
 */
final class WifiBroadcasts extends Resource
{
    /**
     * @return Page<WifiBroadcast>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page($this->sitePath('/wifi/broadcasts'), WifiBroadcast::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, WifiBroadcast>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor($this->sitePath('/wifi/broadcasts'), WifiBroadcast::fromArray(...), $filter, 200);
    }

    public function get(string $broadcastId): WifiBroadcast
    {
        return WifiBroadcast::fromArray($this->api->get($this->sitePath('/wifi/broadcasts' . self::segment($broadcastId))));
    }

    /**
     * @param array<string, mixed> $broadcast
     */
    public function create(array $broadcast): WifiBroadcast
    {
        return WifiBroadcast::fromArray($this->api->post($this->sitePath('/wifi/broadcasts'), $broadcast));
    }

    /**
     * @param array<string, mixed> $broadcast Complete definition (PUT semantics).
     */
    public function update(string $broadcastId, array $broadcast): WifiBroadcast
    {
        return WifiBroadcast::fromArray($this->api->put($this->sitePath('/wifi/broadcasts' . self::segment($broadcastId)), $broadcast));
    }

    public function delete(string $broadcastId): void
    {
        $this->api->delete($this->sitePath('/wifi/broadcasts' . self::segment($broadcastId)));
    }

    public function enable(string $broadcastId): WifiBroadcast
    {
        return $this->toggle($broadcastId, true);
    }

    public function disable(string $broadcastId): WifiBroadcast
    {
        return $this->toggle($broadcastId, false);
    }

    private function toggle(string $broadcastId, bool $enabled): WifiBroadcast
    {
        $definition = $this->get($broadcastId)->raw;
        unset($definition['id'], $definition['metadata']);
        $definition['enabled'] = $enabled;

        /** @var array<string, mixed> $definition */
        return $this->update($broadcastId, $definition);
    }
}
