<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Support\Values;

/**
 * Sites and system information of the classic API.
 */
final class Sites extends ClassicResource
{
    /**
     * Sites the account can access (api/self/sites): _id, name (short name), desc, role.
     *
     * @return list<array<array-key, mixed>>
     */
    public function all(): array
    {
        return Values::rows($this->api->request('GET', '/api/self/sites', idempotent: true));
    }

    /**
     * Sites with health statistics (api/stat/sites).
     *
     * @return list<array<array-key, mixed>>
     */
    public function stats(): array
    {
        return Values::rows($this->api->request('GET', '/api/stat/sites', idempotent: true));
    }

    /**
     * Controller system information of the current site (version, hostname, time zone, ...).
     *
     * @return array<array-key, mixed>
     */
    public function sysinfo(): array
    {
        return Values::rows($this->api->request('GET', $this->path('stat/sysinfo'), idempotent: true))[0] ?? [];
    }

    /**
     * Health of the site subsystems (wan, lan, wlan, www, vpn).
     *
     * @return list<array<array-key, mixed>>
     */
    public function health(): array
    {
        return Values::rows($this->api->request('GET', $this->path('stat/health'), idempotent: true));
    }
}
