<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * Network (VLAN) of a site; management is GATEWAY, SWITCH or UNMANAGED.
 */
final readonly class Network
{
    /**
     * @param array<array-key, mixed> $metadata
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $management,
        public bool $enabled,
        public bool $default,
        public ?int $vlanId,
        public array $metadata = [],
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
            management: Values::string($data, 'management'),
            enabled: Values::bool($data, 'enabled'),
            default: Values::bool($data, 'default'),
            vlanId: Values::nullableInt($data, 'vlanId'),
            metadata: Values::array($data, 'metadata'),
            raw: $data,
        );
    }
}
