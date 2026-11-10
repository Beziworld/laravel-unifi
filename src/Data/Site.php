<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * Site of the Network application (official API).
 *
 * The internal reference is the short site name used by the classic API
 * and by the controller URLs, e.g. "default".
 */
final readonly class Site
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $internalReference,
        public string $name,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data, 'id'),
            internalReference: Values::string($data, 'internalReference'),
            name: Values::string($data, 'name'),
            raw: $data,
        );
    }
}
