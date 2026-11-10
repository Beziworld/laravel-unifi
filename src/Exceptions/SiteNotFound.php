<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

/**
 * Raised when the configured site reference does not match any site of the controller.
 */
final class SiteNotFound extends UnifiException
{
    public static function forReference(string $reference, string $connection): self
    {
        return new self(sprintf('UniFi connection "%s" has no site with the internal reference "%s". Set UNIFI_SITE_ID to the site UUID or UNIFI_SITE to an existing short name.', $connection, $reference));
    }
}
