<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Hotspot guest authorization granted to or revoked from a client.
 */
final readonly class GuestAuthorization
{
    /**
     * @param string $method VOUCHER, API or OTHER.
     * @param array<array-key, mixed> $usage
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $method,
        public ?CarbonImmutable $authorizedAt,
        public ?CarbonImmutable $expiresAt,
        public ?int $dataUsageLimitMBytes,
        public ?int $rxRateLimitKbps,
        public ?int $txRateLimitKbps,
        public array $usage = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: Values::string($data, 'authorizationMethod'),
            authorizedAt: Values::date($data, 'authorizedAt'),
            expiresAt: Values::date($data, 'expiresAt'),
            dataUsageLimitMBytes: Values::nullableInt($data, 'dataUsageLimitMBytes'),
            rxRateLimitKbps: Values::nullableInt($data, 'rxRateLimitKbps'),
            txRateLimitKbps: Values::nullableInt($data, 'txRateLimitKbps'),
            usage: Values::array($data, 'usage'),
            raw: $data,
        );
    }
}
