<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Client (station) of the classic API (stat/sta, stat/alluser, stat/guest).
 */
final readonly class ClassicClient
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $mac,
        public ?string $id,
        public ?string $name,
        public ?string $hostname,
        public ?string $ip,
        public ?string $essid,
        public ?string $apMac,
        public bool $isGuest,
        public bool $isWired,
        public bool $authorized,
        public bool $blocked,
        public ?int $rxBytes,
        public ?int $txBytes,
        public ?CarbonImmutable $firstSeen,
        public ?CarbonImmutable $lastSeen,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mac: strtolower(Values::string($data, 'mac')),
            id: Values::nullableString($data, '_id'),
            name: Values::nullableString($data, 'name'),
            hostname: Values::nullableString($data, 'hostname'),
            ip: Values::nullableString($data, 'ip'),
            essid: Values::nullableString($data, 'essid'),
            apMac: Values::nullableString($data, 'ap_mac'),
            isGuest: Values::bool($data, 'is_guest'),
            isWired: Values::bool($data, 'is_wired'),
            authorized: Values::bool($data, 'authorized'),
            blocked: Values::bool($data, 'blocked'),
            rxBytes: Values::nullableInt($data, 'rx_bytes'),
            txBytes: Values::nullableInt($data, 'tx_bytes'),
            firstSeen: Values::timestamp($data, 'first_seen'),
            lastSeen: Values::timestamp($data, 'last_seen'),
            raw: $data,
        );
    }

    /**
     * Name shown in the controller: the alias, otherwise the hostname, otherwise the MAC address.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->hostname ?? $this->mac;
    }
}
