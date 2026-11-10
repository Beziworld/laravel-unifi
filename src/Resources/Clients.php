<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\ConnectedClient;
use Beziworld\Unifi\Data\GuestAccessResult;
use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Support\Filter;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

/**
 * Connected clients of a site and hotspot guest authorization.
 */
final class Clients extends Resource
{
    /**
     * @param Filter|string|null $filter e.g. Filter::eq('access.type', 'GUEST') or Filter::eq('macAddress', 'aa:bb:...').
     * @return Page<ConnectedClient>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page($this->sitePath('/clients'), ConnectedClient::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, ConnectedClient>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor($this->sitePath('/clients'), ConnectedClient::fromArray(...), $filter, 200);
    }

    /**
     * Guest clients (wired and wireless) currently connected.
     *
     * @return LazyCollection<int, ConnectedClient>
     */
    public function guests(): LazyCollection
    {
        return $this->all(Filter::eq('access.type', 'GUEST'));
    }

    public function get(string $clientId): ConnectedClient
    {
        return ConnectedClient::fromArray($this->api->get($this->sitePath('/clients' . self::segment($clientId))));
    }

    /**
     * Finds a connected client by its MAC address.
     */
    public function findByMac(string $macAddress): ?ConnectedClient
    {
        return $this->list(Filter::eq('macAddress', strtolower($macAddress)), 0, 1)->first();
    }

    /**
     * Authorizes a guest client on the hotspot, optionally with time, data and rate limits.
     *
     * @param int|null $minutes Authorization length (1..1,000,000); the hotspot default applies when null.
     * @param int|null $megabytes Data limit (1..1,048,576 MB).
     * @param int|null $downKbps Download (rx) rate limit (2..100,000 kbps).
     * @param int|null $upKbps Upload (tx) rate limit (2..100,000 kbps).
     */
    public function authorizeGuest(string $clientId, ?int $minutes = null, ?int $megabytes = null, ?int $downKbps = null, ?int $upKbps = null): GuestAccessResult
    {
        self::range('minutes', $minutes, 1, 1_000_000);
        self::range('megabytes', $megabytes, 1, 1_048_576);
        self::range('downKbps', $downKbps, 2, 100_000);
        self::range('upKbps', $upKbps, 2, 100_000);

        return GuestAccessResult::fromArray($this->action($clientId, array_filter([
            'action' => 'AUTHORIZE_GUEST_ACCESS',
            'timeLimitMinutes' => $minutes,
            'dataUsageLimitMBytes' => $megabytes,
            'rxRateLimitKbps' => $downKbps,
            'txRateLimitKbps' => $upKbps,
        ], static fn(int|string|null $value): bool => $value !== null)));
    }

    /**
     * Revokes the hotspot authorization of a guest client.
     */
    public function unauthorizeGuest(string $clientId): GuestAccessResult
    {
        return GuestAccessResult::fromArray($this->action($clientId, ['action' => 'UNAUTHORIZE_GUEST_ACCESS']));
    }

    /**
     * @param array<string, int|string> $body
     * @return array<array-key, mixed>
     */
    private function action(string $clientId, array $body): array
    {
        return $this->api->post($this->sitePath('/clients' . self::segment($clientId) . '/actions'), $body);
    }

    private static function range(string $field, ?int $value, int $min, int $max): void
    {
        if ($value !== null && ($value < $min || $value > $max)) {
            throw new InvalidArgumentException(sprintf('The guest authorization %s must be between %d and %d, %d given.', $field, $min, $max, $value));
        }
    }
}
