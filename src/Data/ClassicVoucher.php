<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Hotspot voucher of the classic API (stat/voucher).
 *
 * Quota 0 means multi-use without limit, 1 single use and n a voucher for
 * n uses; duration is expressed in minutes.
 */
final readonly class ClassicVoucher
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $code,
        public int $durationMinutes,
        public int $quota,
        public int $used,
        public ?string $note,
        public ?string $status,
        public ?int $upKbps,
        public ?int $downKbps,
        public ?int $megabytes,
        public ?CarbonImmutable $createdAt,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data, '_id'),
            code: Values::string($data, 'code'),
            durationMinutes: Values::int($data, 'duration'),
            quota: Values::int($data, 'quota'),
            used: Values::int($data, 'used'),
            note: Values::nullableString($data, 'note'),
            status: Values::nullableString($data, 'status'),
            upKbps: Values::nullableInt($data, 'qos_rate_max_up'),
            downKbps: Values::nullableInt($data, 'qos_rate_max_down'),
            megabytes: Values::nullableInt($data, 'qos_usage_quota'),
            createdAt: Values::timestamp($data, 'create_time'),
            raw: $data,
        );
    }

    /**
     * Code split into two groups of five digits ("12345-67890").
     */
    public function formattedCode(): string
    {
        return strlen($this->code) === 10 && ctype_digit($this->code)
            ? substr($this->code, 0, 5) . '-' . substr($this->code, 5)
            : $this->code;
    }
}
