<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Hotspot voucher (official API).
 *
 * The time limit counts from the first activation; authorizedGuestLimit is
 * the number of devices that may use the voucher (null: unlimited).
 */
final readonly class Voucher
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public int $timeLimitMinutes,
        public bool $expired,
        public int $authorizedGuestCount,
        public ?int $authorizedGuestLimit,
        public ?int $dataUsageLimitMBytes,
        public ?int $rxRateLimitKbps,
        public ?int $txRateLimitKbps,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $activatedAt,
        public ?CarbonImmutable $expiresAt,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data, 'id'),
            code: Values::string($data, 'code'),
            name: Values::string($data, 'name'),
            timeLimitMinutes: Values::int($data, 'timeLimitMinutes'),
            expired: Values::bool($data, 'expired'),
            authorizedGuestCount: Values::int($data, 'authorizedGuestCount'),
            authorizedGuestLimit: Values::nullableInt($data, 'authorizedGuestLimit'),
            dataUsageLimitMBytes: Values::nullableInt($data, 'dataUsageLimitMBytes'),
            rxRateLimitKbps: Values::nullableInt($data, 'rxRateLimitKbps'),
            txRateLimitKbps: Values::nullableInt($data, 'txRateLimitKbps'),
            createdAt: Values::date($data, 'createdAt'),
            activatedAt: Values::date($data, 'activatedAt'),
            expiresAt: Values::date($data, 'expiresAt'),
            raw: $data,
        );
    }

    /**
     * Code split into two groups of five digits, as printed by the UniFi hotspot ("12345-67890").
     */
    public function formattedCode(): string
    {
        return strlen($this->code) === 10 && ctype_digit($this->code)
            ? substr($this->code, 0, 5) . '-' . substr($this->code, 5)
            : $this->code;
    }

    public function isActivated(): bool
    {
        return $this->activatedAt !== null;
    }

    public function hasFreeSlots(): bool
    {
        return ! $this->expired && ($this->authorizedGuestLimit === null || $this->authorizedGuestCount < $this->authorizedGuestLimit);
    }
}
