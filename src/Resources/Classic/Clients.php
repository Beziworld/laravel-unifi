<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Data\ClassicClient;
use Beziworld\Unifi\Support\Values;

/**
 * Clients (stations) and hotspot guests of the classic API (stat/sta, cmd/stamgr).
 */
final class Clients extends ClassicResource
{
    /**
     * Clients currently online.
     *
     * @return list<ClassicClient>
     */
    public function online(): array
    {
        return array_map(ClassicClient::fromArray(...), Values::rows($this->api->request('GET', $this->path('stat/sta'), idempotent: true)));
    }

    /**
     * Online client by MAC address, null when it is not connected.
     */
    public function find(string $mac): ?ClassicClient
    {
        $rows = Values::rows($this->api->request('GET', $this->path('stat/sta/' . self::mac($mac)), idempotent: true));

        return $rows === [] ? null : ClassicClient::fromArray($rows[0]);
    }

    /**
     * Every client seen within the given number of hours, online or not.
     *
     * @return list<ClassicClient>
     */
    public function known(int $withinHours = 8760): array
    {
        return array_map(ClassicClient::fromArray(...), Values::rows($this->api->request('POST', $this->path('stat/alluser'), [
            'type' => 'all',
            'conn' => 'all',
            'within' => $withinHours,
        ], idempotent: true)));
    }

    /**
     * Guests with a valid hotspot authorization within the given number of hours.
     *
     * @return list<ClassicClient>
     */
    public function guests(int $withinHours = 8760): array
    {
        return array_map(ClassicClient::fromArray(...), Values::rows($this->api->request('POST', $this->path('stat/guest'), [
            'within' => $withinHours,
        ], idempotent: true)));
    }

    /**
     * Authorizes a guest device on the hotspot (cmd/stamgr authorize-guest).
     *
     * @param int $minutes Minutes from now until the authorization expires.
     * @param int|null $upKbps Upload limit.
     * @param int|null $downKbps Download limit.
     * @param int|null $megabytes Data transfer limit in MB.
     * @param string|null $apMac Access point the guest is connected to; speeds up the authorization.
     */
    public function authorizeGuest(string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $megabytes = null, ?string $apMac = null): void
    {
        $this->command('stamgr', 'authorize-guest', array_filter([
            'mac' => self::mac($mac),
            'minutes' => $minutes,
            'up' => $upKbps,
            'down' => $downKbps,
            'bytes' => $megabytes,
            'ap_mac' => $apMac === null ? null : self::mac($apMac),
        ], static fn(int|string|null $value): bool => $value !== null));
    }

    public function unauthorizeGuest(string $mac): void
    {
        $this->command('stamgr', 'unauthorize-guest', ['mac' => self::mac($mac)]);
    }

    /**
     * Disconnects a wireless client, which then reconnects (kick-sta).
     */
    public function reconnect(string $mac): void
    {
        $this->command('stamgr', 'kick-sta', ['mac' => self::mac($mac)]);
    }

    public function block(string $mac): void
    {
        $this->command('stamgr', 'block-sta', ['mac' => self::mac($mac)]);
    }

    public function unblock(string $mac): void
    {
        $this->command('stamgr', 'unblock-sta', ['mac' => self::mac($mac)]);
    }

    /**
     * Removes clients and their history from the controller.
     *
     * @param list<string> $macs
     */
    public function forget(array $macs): void
    {
        $this->command('stamgr', 'forget-sta', ['macs' => self::macs($macs)]);
    }
}
