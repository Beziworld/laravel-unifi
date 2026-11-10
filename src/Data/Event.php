<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Controller event or alarm of the classic API (stat/event, list/alarm).
 *
 * The key identifies the kind of event, e.g. "EVT_WU_Connected" or
 * "EVT_GW_WANTransition"; archived is only meaningful for alarms.
 */
final readonly class Event
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $key,
        public ?string $message,
        public ?string $subsystem,
        public ?string $siteId,
        public bool $archived,
        public ?CarbonImmutable $time,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data, '_id'),
            key: Values::string($data, 'key'),
            message: Values::nullableString($data, 'msg'),
            subsystem: Values::nullableString($data, 'subsystem'),
            siteId: Values::nullableString($data, 'site_id'),
            archived: Values::bool($data, 'archived'),
            time: Values::timestamp($data, 'time') ?? Values::date($data, 'datetime'),
            raw: $data,
        );
    }
}
