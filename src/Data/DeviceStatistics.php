<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Latest statistics of an adopted device.
 */
final readonly class DeviceStatistics
{
    /**
     * @param array<array-key, mixed> $uplink
     * @param array<array-key, mixed> $interfaces
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?int $uptimeSeconds,
        public ?float $cpuUtilizationPct,
        public ?float $memoryUtilizationPct,
        public ?float $loadAverage1Min,
        public ?float $loadAverage5Min,
        public ?float $loadAverage15Min,
        public ?CarbonImmutable $lastHeartbeatAt,
        public ?CarbonImmutable $nextHeartbeatAt,
        public array $uplink = [],
        public array $interfaces = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uptimeSeconds: Values::nullableInt($data, 'uptimeSec'),
            cpuUtilizationPct: Values::nullableFloat($data, 'cpuUtilizationPct'),
            memoryUtilizationPct: Values::nullableFloat($data, 'memoryUtilizationPct'),
            loadAverage1Min: Values::nullableFloat($data, 'loadAverage1Min'),
            loadAverage5Min: Values::nullableFloat($data, 'loadAverage5Min'),
            loadAverage15Min: Values::nullableFloat($data, 'loadAverage15Min'),
            lastHeartbeatAt: Values::date($data, 'lastHeartbeatAt'),
            nextHeartbeatAt: Values::date($data, 'nextHeartbeatAt'),
            uplink: Values::array($data, 'uplink'),
            interfaces: Values::array($data, 'interfaces'),
            raw: $data,
        );
    }
}
