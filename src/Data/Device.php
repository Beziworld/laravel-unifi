<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * Adopted or pending UniFi device (access point, switch, gateway).
 *
 * Pending devices have no id and no name; they carry the sites they can be
 * adopted into instead.
 */
final readonly class Device
{
    public const string ONLINE = 'ONLINE';

    public const string OFFLINE = 'OFFLINE';

    /**
     * @param list<string> $features switching, accessPoint, gateway
     * @param list<string> $interfaces ports, radios
     * @param list<string> $adoptionTargetSiteIds
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?string $id,
        public ?string $name,
        public string $model,
        public string $macAddress,
        public ?string $ipAddress,
        public string $state,
        public ?string $firmwareVersion,
        public bool $firmwareUpdatable,
        public bool $supported,
        public array $features = [],
        public array $interfaces = [],
        public array $adoptionTargetSiteIds = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::nullableString($data, 'id'),
            name: Values::nullableString($data, 'name'),
            model: Values::string($data, 'model'),
            macAddress: strtolower(Values::string($data, 'macAddress')),
            ipAddress: Values::nullableString($data, 'ipAddress'),
            state: Values::string($data, 'state'),
            firmwareVersion: Values::nullableString($data, 'firmwareVersion'),
            firmwareUpdatable: Values::bool($data, 'firmwareUpdatable'),
            supported: Values::bool($data, 'supported', true),
            features: Values::strings($data, 'features'),
            interfaces: Values::strings($data, 'interfaces'),
            adoptionTargetSiteIds: Values::strings($data, 'adoptionTargetSiteIds'),
            raw: $data,
        );
    }

    public function isOnline(): bool
    {
        return $this->state === self::ONLINE;
    }

    public function isAccessPoint(): bool
    {
        return in_array('accessPoint', $this->features, true);
    }

    public function isSwitch(): bool
    {
        return in_array('switching', $this->features, true);
    }

    public function isGateway(): bool
    {
        return in_array('gateway', $this->features, true);
    }
}
