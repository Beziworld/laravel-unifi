<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Support\Values;
use InvalidArgumentException;

/**
 * WLAN and network configuration of the classic API (rest/wlanconf, rest/networkconf).
 */
final class Wlans extends ClassicResource
{
    /**
     * @return list<array<array-key, mixed>>
     */
    public function all(): array
    {
        return Values::rows($this->api->request('GET', $this->path('rest/wlanconf'), idempotent: true));
    }

    /**
     * @return array<array-key, mixed>
     */
    public function get(string $wlanId): array
    {
        return Values::rows($this->api->request('GET', $this->path('rest/wlanconf/' . rawurlencode($wlanId)), idempotent: true))[0] ?? [];
    }

    /**
     * Updates selected fields of a WLAN, e.g. ["enabled" => false] or ["x_passphrase" => "..."].
     *
     * @param array<string, mixed> $changes
     */
    public function update(string $wlanId, array $changes): void
    {
        $this->api->request('PUT', $this->path('rest/wlanconf/' . rawurlencode($wlanId)), $changes);
    }

    public function enable(string $wlanId, bool $enabled = true): void
    {
        $this->update($wlanId, ['enabled' => $enabled]);
    }

    /**
     * Changes the pre-shared key (8 to 63 characters), e.g. for a daily rotated guest network password.
     */
    public function setPassphrase(string $wlanId, #[\SensitiveParameter] string $passphrase): void
    {
        $length = strlen($passphrase);

        if ($length < 8 || $length > 63) {
            throw new InvalidArgumentException('A WPA passphrase must have between 8 and 63 characters.');
        }

        $this->update($wlanId, ['x_passphrase' => $passphrase]);
    }

    /**
     * Networks (LAN, VLAN, WAN, VPN) of the site.
     *
     * @return list<array<array-key, mixed>>
     */
    public function networks(): array
    {
        return Values::rows($this->api->request('GET', $this->path('rest/networkconf'), idempotent: true));
    }
}
