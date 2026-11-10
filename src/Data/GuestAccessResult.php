<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Data;

use Beziworld\Unifi\Support\Values;

/**
 * Result of the AUTHORIZE_GUEST_ACCESS and UNAUTHORIZE_GUEST_ACCESS client actions.
 */
final readonly class GuestAccessResult
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $action,
        public ?GuestAuthorization $granted,
        public ?GuestAuthorization $revoked,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $granted = Values::array($data, 'grantedAuthorization');
        $revoked = Values::array($data, 'revokedAuthorization');

        return new self(
            action: Values::string($data, 'action'),
            granted: $granted === [] ? null : GuestAuthorization::fromArray($granted),
            revoked: $revoked === [] ? null : GuestAuthorization::fromArray($revoked),
            raw: $data,
        );
    }
}
