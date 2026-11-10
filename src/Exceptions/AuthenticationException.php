<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Exceptions;

/**
 * Raised when the controller rejects the API key or the login credentials (HTTP 401/403).
 *
 * Failed logins are never repeated automatically: UniFi OS throttles and
 * temporarily blocks clients after repeated authentication failures.
 */
final class AuthenticationException extends ApiException {}
