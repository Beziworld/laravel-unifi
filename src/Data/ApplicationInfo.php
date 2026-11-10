<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * General information about the Network application (GET /v1/info).
 */
final readonly class ApplicationInfo
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $applicationVersion,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::string($data, 'applicationVersion'), $data);
    }

    public function versionAtLeast(string $version): bool
    {
        return version_compare($this->applicationVersion, $version, '>=');
    }
}
