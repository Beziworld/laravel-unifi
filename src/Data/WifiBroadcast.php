<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * Wi-Fi broadcast (SSID) of a site; type is STANDARD or IOT_OPTIMIZED.
 */
final readonly class WifiBroadcast
{
    /**
     * @param array<array-key, mixed> $network
     * @param array<array-key, mixed> $securityConfiguration
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public bool $enabled,
        public array $network = [],
        public array $securityConfiguration = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data, 'id'),
            name: Values::string($data, 'name'),
            type: Values::string($data, 'type'),
            enabled: Values::bool($data, 'enabled'),
            network: Values::array($data, 'network'),
            securityConfiguration: Values::array($data, 'securityConfiguration'),
            raw: $data,
        );
    }

    /**
     * Security type, e.g. "OPEN", "WPA2_PERSONAL" or "WPA2_WPA3_PERSONAL".
     */
    public function securityType(): ?string
    {
        return Values::nullableString($this->securityConfiguration, 'type');
    }

    /**
     * Whether the SSID serves the hotspot (captive portal) of the site.
     */
    public function isHotspot(): bool
    {
        return Values::array($this->raw, 'hotspotConfiguration') !== [];
    }
}
