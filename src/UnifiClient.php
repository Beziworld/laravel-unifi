<?php

declare(strict_types=1);

namespace Beziworld\Unifi;

use Beziworld\Unifi\Data\ApplicationInfo;
use Beziworld\Unifi\Exceptions\SiteNotFound;
use Beziworld\Unifi\Http\ClassicApi;
use Beziworld\Unifi\Http\IntegrationApi;
use Beziworld\Unifi\Resources\Clients;
use Beziworld\Unifi\Resources\Devices;
use Beziworld\Unifi\Resources\Networks;
use Beziworld\Unifi\Resources\Sites;
use Beziworld\Unifi\Resources\Vouchers;
use Beziworld\Unifi\Resources\WifiBroadcasts;
use Closure;

/**
 * Entry point to one UniFi controller and one of its sites.
 *
 * The official Network API (API key) is exposed directly: sites(),
 * devices(), clients(), vouchers(), networks(), wifi(). The classic session
 * API is reached through classic(). The site UUID of the official API is
 * taken from the configuration or resolved once from the short site name.
 */
class UnifiClient
{
    private ?string $resolvedSiteId;

    public function __construct(
        private readonly IntegrationApi $integration,
        private readonly ClassicApi $classic,
    ) {
        $this->resolvedSiteId = $integration->config()->siteId;
    }

    public function config(): UnifiConfig
    {
        return $this->integration->config();
    }

    /**
     * Client bound to another site of the same controller.
     *
     * @param string $site Site UUID (official API) or short name (classic API, e.g. "default").
     */
    public function forSite(string $site): self
    {
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $site) === 1;

        return $this->withConfig($isUuid
            ? $this->config()->withSite($site)
            : $this->config()->withSite(null, $site));
    }

    /**
     * Site UUID used by the official API resources.
     *
     * @throws SiteNotFound When no site matches the configured short name.
     */
    public function siteId(): string
    {
        if ($this->resolvedSiteId === null) {
            $site = $this->sites()->findByReference($this->config()->site)
                ?? throw SiteNotFound::forReference($this->config()->site, $this->config()->name);

            $this->resolvedSiteId = $site->id;
        }

        return $this->resolvedSiteId;
    }

    public function info(): ApplicationInfo
    {
        return ApplicationInfo::fromArray($this->integration->get('/v1/info'));
    }

    public function sites(): Sites
    {
        return new Sites($this->integration, static fn(): string => '');
    }

    public function devices(): Devices
    {
        return new Devices($this->integration, $this->siteResolver());
    }

    public function clients(): Clients
    {
        return new Clients($this->integration, $this->siteResolver());
    }

    /**
     * Hotspot vouchers.
     */
    public function vouchers(): Vouchers
    {
        return new Vouchers($this->integration, $this->siteResolver());
    }

    public function networks(): Networks
    {
        return new Networks($this->integration, $this->siteResolver());
    }

    /**
     * Wi-Fi broadcasts (SSIDs).
     */
    public function wifi(): WifiBroadcasts
    {
        return new WifiBroadcasts($this->integration, $this->siteResolver());
    }

    /**
     * Classic session API of the configured site, or of the given short site name.
     */
    public function classic(?string $site = null): Classic
    {
        return new Classic($this->classic, $site ?? $this->config()->site);
    }

    /**
     * Sends a request to any endpoint of the official API, e.g. firewall policies or ACL rules.
     *
     * Paths may contain "{siteId}", which is replaced with the current site UUID.
     *
     * @param array<string, scalar|null> $query
     * @param array<array-key, mixed>|null $body
     * @return array<array-key, mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (str_contains($path, '{siteId}')) {
            $path = str_replace('{siteId}', rawurlencode($this->siteId()), $path);
        }

        return $this->integration->send($method, $path, $query, $body);
    }

    public function integration(): IntegrationApi
    {
        return $this->integration;
    }

    protected function withConfig(UnifiConfig $config): self
    {
        return new self(
            $this->integration->withConfig($config),
            $this->classic->withConfig($config),
        );
    }

    /**
     * @return Closure(): string
     */
    private function siteResolver(): Closure
    {
        return fn(): string => $this->siteId();
    }
}
