<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Parameters of a hotspot voucher batch (POST /v1/sites/{siteId}/hotspot/vouchers).
 *
 * Bounds follow the official schema: count 1..1000, time limit
 * 1..1,000,000 minutes, data limit 1..1,048,576 MB, rate limits
 * 2..100,000 kbps, guest limit at least 1.
 */
final readonly class VoucherRequest
{
    public function __construct(
        public string $name,
        public int $timeLimitMinutes,
        public int $count = 1,
        public ?int $authorizedGuestLimit = 1,
        public ?int $dataUsageLimitMBytes = null,
        public ?int $rxRateLimitKbps = null,
        public ?int $txRateLimitKbps = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('The voucher name must not be empty.');
        }

        self::range('timeLimitMinutes', $timeLimitMinutes, 1, 1_000_000);
        self::range('count', $count, 1, 1000);
        self::range('authorizedGuestLimit', $authorizedGuestLimit, 1, PHP_INT_MAX);
        self::range('dataUsageLimitMBytes', $dataUsageLimitMBytes, 1, 1_048_576);
        self::range('rxRateLimitKbps', $rxRateLimitKbps, 2, 100_000);
        self::range('txRateLimitKbps', $txRateLimitKbps, 2, 100_000);
    }

    /**
     * Voucher valid from now until the guest's check-out (rounded up to whole minutes).
     *
     * @param int|null $devices Number of devices allowed per voucher (null: unlimited).
     */
    public static function untilCheckout(string $name, DateTimeInterface $checkout, ?int $devices = 1, int $count = 1, ?DateTimeInterface $now = null): self
    {
        $start = $now === null ? CarbonImmutable::now() : CarbonImmutable::instance($now);
        $seconds = CarbonImmutable::instance($checkout)->getTimestamp() - $start->getTimestamp();

        if ($seconds <= 0) {
            throw new InvalidArgumentException('The check-out time must be in the future.');
        }

        return new self($name, (int) ceil($seconds / 60), $count, $devices);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'count' => $this->count,
            'timeLimitMinutes' => $this->timeLimitMinutes,
            'authorizedGuestLimit' => $this->authorizedGuestLimit,
            'dataUsageLimitMBytes' => $this->dataUsageLimitMBytes,
            'rxRateLimitKbps' => $this->rxRateLimitKbps,
            'txRateLimitKbps' => $this->txRateLimitKbps,
        ], static fn(int|string|null $value): bool => $value !== null);
    }

    private static function range(string $field, ?int $value, int $min, int $max): void
    {
        if ($value !== null && ($value < $min || $value > $max)) {
            throw new InvalidArgumentException(sprintf('The voucher %s must be between %d and %d, %d given.', $field, $min, $max, $value));
        }
    }
}
