<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Client connected to the network (official API): wired, wireless, VPN or Teleport.
 */
final readonly class ConnectedClient
{
    public const string WIRED = 'WIRED';

    public const string WIRELESS = 'WIRELESS';

    public const string VPN = 'VPN';

    public const string TELEPORT = 'TELEPORT';

    /**
     * @param array<array-key, mixed> $access Access descriptor, e.g. {"type": "DEFAULT"} or guest authorization details.
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public ?string $macAddress,
        public ?string $ipAddress,
        public ?string $uplinkDeviceId,
        public ?CarbonImmutable $connectedAt,
        public array $access = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $mac = Values::nullableString($data, 'macAddress');

        return new self(
            id: Values::string($data, 'id'),
            name: Values::string($data, 'name'),
            type: Values::string($data, 'type'),
            macAddress: $mac === null ? null : strtolower($mac),
            ipAddress: Values::nullableString($data, 'ipAddress'),
            uplinkDeviceId: Values::nullableString($data, 'uplinkDeviceId'),
            connectedAt: Values::date($data, 'connectedAt'),
            access: Values::array($data, 'access'),
            raw: $data,
        );
    }

    public function isWireless(): bool
    {
        return $this->type === self::WIRELESS;
    }

    /**
     * Access type reported by the controller, e.g. "DEFAULT" or "GUEST".
     */
    public function accessType(): ?string
    {
        return Values::nullableString($this->access, 'type');
    }

    public function isGuest(): bool
    {
        return $this->accessType() === 'GUEST';
    }

    /**
     * Whether a guest client passed the hotspot (voucher, API or portal authorization).
     */
    public function isAuthorizedGuest(): bool
    {
        return $this->isGuest() && Values::bool($this->access, 'authorized');
    }
}
