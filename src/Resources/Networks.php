<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Network;
use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Support\Filter;
use Beziworld\Unifi\Support\Values;
use Illuminate\Support\LazyCollection;

/**
 * Networks (VLANs) and WAN interfaces of a site.
 *
 * Create and update bodies follow the "Create or update Network" schema of
 * the official API (management, name, enabled, vlanId and the
 * management-specific settings) and are passed through unchanged.
 */
final class Networks extends Resource
{
    /**
     * @return Page<Network>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page($this->sitePath('/networks'), Network::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, Network>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor($this->sitePath('/networks'), Network::fromArray(...), $filter, 200);
    }

    public function get(string $networkId): Network
    {
        return Network::fromArray($this->api->get($this->sitePath('/networks' . self::segment($networkId))));
    }

    /**
     * @param array<string, mixed> $network
     */
    public function create(array $network): Network
    {
        return Network::fromArray($this->api->post($this->sitePath('/networks'), $network));
    }

    /**
     * @param array<string, mixed> $network Complete network definition (PUT semantics).
     */
    public function update(string $networkId, array $network): Network
    {
        return Network::fromArray($this->api->put($this->sitePath('/networks' . self::segment($networkId)), $network));
    }

    public function delete(string $networkId): void
    {
        $this->api->delete($this->sitePath('/networks' . self::segment($networkId)));
    }

    /**
     * Resources that reference the network (Wi-Fi broadcasts, firewall zones, ...), useful before deleting it.
     *
     * @return array<array-key, mixed>
     */
    public function references(string $networkId): array
    {
        return $this->api->get($this->sitePath('/networks' . self::segment($networkId) . '/references'));
    }

    /**
     * WAN interfaces of the site's gateway as id/name pairs.
     *
     * @return array<string, string>
     */
    public function wans(): array
    {
        $wans = [];

        foreach (Values::rows($this->api->get($this->sitePath('/wans'), ['limit' => 200])['data'] ?? []) as $wan) {
            $wans[Values::string($wan, 'id')] = Values::string($wan, 'name');
        }

        return $wans;
    }
}
