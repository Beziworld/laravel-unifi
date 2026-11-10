<?php

declare(strict_types=1);

namespace Beziworld\Unifi;

use Beziworld\Unifi\Http\ClassicApi;
use Beziworld\Unifi\Resources\Classic\Clients;
use Beziworld\Unifi\Resources\Classic\Devices;
use Beziworld\Unifi\Resources\Classic\Events;
use Beziworld\Unifi\Resources\Classic\Sites;
use Beziworld\Unifi\Resources\Classic\Vouchers;
use Beziworld\Unifi\Resources\Classic\Wlans;

/**
 * Classic (session based) controller API of one site, grouped by topic.
 *
 * Use it for events and alarms, client blocking and reconnecting, WLAN
 * passphrases and controllers older than Network 9, which have no official
 * API.
 */
final class Classic
{
    public function __construct(
        private readonly ClassicApi $api,
        private readonly string $site,
    ) {}

    /**
     * Same session, another site (short name, e.g. "hotel2").
     */
    public function site(string $site): self
    {
        return new self($this->api, $site);
    }

    public function siteName(): string
    {
        return $this->site;
    }

    public function api(): ClassicApi
    {
        return $this->api;
    }

    public function sites(): Sites
    {
        return new Sites($this->api, $this->site);
    }

    public function clients(): Clients
    {
        return new Clients($this->api, $this->site);
    }

    public function vouchers(): Vouchers
    {
        return new Vouchers($this->api, $this->site);
    }

    public function devices(): Devices
    {
        return new Devices($this->api, $this->site);
    }

    public function wlans(): Wlans
    {
        return new Wlans($this->api, $this->site);
    }

    public function events(): Events
    {
        return new Events($this->api, $this->site);
    }

    /**
     * Sends a request to any classic endpoint of the current site, e.g. request('GET', 'stat/health').
     *
     * @param array<array-key, mixed>|null $payload
     * @return array<array-key, mixed>
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $path = str_starts_with($path, '/') ? $path : '/api/s/' . rawurlencode($this->site) . '/' . $path;

        return $this->api->request($method, $path, $payload, strtoupper($method) === 'GET');
    }

    public function login(): void
    {
        $this->api->login();
    }

    public function logout(): void
    {
        $this->api->logout();
    }
}
