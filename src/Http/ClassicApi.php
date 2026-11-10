<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Http;

use Beziworld\Unifi\Exceptions\ApiException;
use Beziworld\Unifi\Exceptions\AuthenticationException;
use Beziworld\Unifi\Exceptions\ConnectionFailed;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Exceptions\RateLimitException;
use Beziworld\Unifi\UnifiConfig;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * HTTP transport of the classic (session based) UniFi controller API.
 *
 * UniFi OS consoles authenticate at /api/auth/login, return a TOKEN cookie
 * and require an x-csrf-token header on state-changing requests; the
 * Network application is proxied under /proxy/network. Legacy controllers
 * authenticate at /api/login with a "unifises" cookie and serve the API at
 * the root. Sessions are kept in the cache; an expired session (HTTP 401)
 * is renewed once, which is safe because the rejected request was not
 * executed.
 */
class ClassicApi
{
    /** @var array{cookie: string, csrf: ?string, unifi_os: bool}|null */
    private ?array $session = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly UnifiConfig $config,
        private readonly Cache $cache,
        private readonly string $cachePrefix = 'unifi:session:',
        private readonly int $sessionTtl = 3600,
    ) {}

    public function config(): UnifiConfig
    {
        return $this->config;
    }

    /**
     * Transport for another site of the same controller; the cached session is shared.
     */
    public function withConfig(UnifiConfig $config): self
    {
        return new self($this->http, $config, $this->cache, $this->cachePrefix, $this->sessionTtl);
    }

    /**
     * Sends a request to the classic API and returns the "data" member of the envelope.
     *
     * @param string $path Path such as "/api/s/default/stat/sta" (the /proxy/network prefix is added when needed).
     * @param array<array-key, mixed>|null $payload JSON body.
     * @param bool $idempotent Whether the request only reads data and may be repeated after a transport error.
     * @return array<array-key, mixed>
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @throws ConnectionFailed
     */
    public function request(string $method, string $path, ?array $payload = null, bool $idempotent = false): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $operation = $method . ' ' . $path;
        $attempts = $idempotent ? $this->config->retries + 1 : 1;
        $renewed = false;

        for ($attempt = 1; ; $attempt++) {
            $session = $this->session();

            try {
                $response = $this->send($session, $method, $path, $payload);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    $this->pause($attempt);

                    continue;
                }

                throw ConnectionFailed::during($operation, (string) $this->config->url, $exception);
            }

            if ($response->status() === 401 && ! $renewed) {
                $renewed = true;
                $this->forgetSession();
                $attempt--;

                continue;
            }

            if ($response->serverError() && $attempt < $attempts) {
                $this->pause($attempt);

                continue;
            }

            $this->rememberCsrf($response);

            return $this->decode($operation, $response);
        }
    }

    /**
     * Authenticates explicitly and stores the session; normally done lazily by request().
     */
    public function login(): void
    {
        $this->forgetSession();
        $this->session();
    }

    /**
     * Ends the session on the controller and forgets it locally.
     */
    public function logout(): void
    {
        $session = $this->session ?? $this->cachedSession();

        if ($session !== null) {
            try {
                $request = $this->base($session);

                if ($session['unifi_os'] && $session['csrf'] !== null) {
                    $request = $request->withHeaders(['X-CSRF-Token' => $session['csrf']]);
                }

                $request->post((string) $this->config->url . ($session['unifi_os'] ? '/api/auth/logout' : '/logout'));
            } catch (ConnectionException) {
                // The local session is discarded even when the controller is unreachable.
            }
        }

        $this->forgetSession();
    }

    /**
     * Whether the controller runs on UniFi OS (detected during login unless configured).
     */
    public function isUnifiOs(): bool
    {
        return $this->session()['unifi_os'];
    }

    /**
     * @return array{cookie: string, csrf: ?string, unifi_os: bool}
     */
    private function session(): array
    {
        return $this->session ??= $this->cachedSession() ?? $this->authenticate();
    }

    /**
     * @return array{cookie: string, csrf: ?string, unifi_os: bool}|null
     */
    private function cachedSession(): ?array
    {
        $cached = $this->cache->get($this->cacheKey());

        if (! is_array($cached) || ! is_string($cached['cookie'] ?? null) || ! is_bool($cached['unifi_os'] ?? null)) {
            return null;
        }

        return [
            'cookie' => $cached['cookie'],
            'csrf' => is_string($cached['csrf'] ?? null) ? $cached['csrf'] : null,
            'unifi_os' => $cached['unifi_os'],
        ];
    }

    private function forgetSession(): void
    {
        $this->session = null;
        $this->cache->forget($this->cacheKey());
    }

    /**
     * @return array{cookie: string, csrf: ?string, unifi_os: bool}
     */
    private function authenticate(): array
    {
        if (! $this->config->hasCredentials()) {
            throw InvalidConfiguration::credentialsRequired($this->config->name);
        }

        $unifiOs = $this->detectUnifiOs();
        $path = $unifiOs ? '/api/auth/login' : '/api/login';
        $operation = 'POST ' . $path;

        try {
            $response = $this->base(null)
                ->withHeaders(['Referer' => $this->config->url . '/login'])
                ->post($this->config->url . $path, [
                    'username' => $this->config->username,
                    'password' => $this->config->password,
                    'remember' => true,
                ]);
        } catch (ConnectionException $exception) {
            throw ConnectionFailed::during($operation, (string) $this->config->url, $exception);
        }

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        if ($response->status() === 429) {
            throw RateLimitException::fromResponse($operation, $response);
        }

        if ($response->status() === 400 || $response->status() === 401 || $response->status() === 403) {
            throw AuthenticationException::fromPayload($operation, $response->status(), $payload + ['message' => 'Login rejected for user ' . $this->config->username]);
        }

        if ($response->failed()) {
            throw ApiException::fromPayload($operation, $response->status(), $payload);
        }

        $cookies = self::cookies($response);
        $name = isset($cookies['TOKEN']) ? 'TOKEN' : (isset($cookies['unifises']) ? 'unifises' : null);

        if ($name === null) {
            throw AuthenticationException::fromPayload($operation, $response->status(), ['message' => 'The controller did not return a session cookie']);
        }

        $cookie = $name . '=' . $cookies[$name];
        $csrf = trim($response->header('X-CSRF-Token'));

        if ($csrf === '' && isset($cookies['csrf_token'])) {
            $cookie .= '; csrf_token=' . $cookies['csrf_token'];
            $csrf = $cookies['csrf_token'];
        }

        $session = [
            'cookie' => $cookie,
            'csrf' => $csrf !== '' ? $csrf : ($name === 'TOKEN' ? self::csrfFromJwt($cookies['TOKEN']) : null),
            'unifi_os' => $name === 'TOKEN' || $unifiOs,
        ];

        $this->cache->put($this->cacheKey(), $session, $this->sessionTtl);

        return $session;
    }

    private function detectUnifiOs(): bool
    {
        return match ($this->config->controller) {
            UnifiConfig::CONTROLLER_UNIFI_OS => true,
            UnifiConfig::CONTROLLER_LEGACY => false,
            default => $this->probe(),
        };
    }

    /**
     * UniFi OS answers the root URL with HTTP 200, legacy controllers redirect to the login page.
     */
    private function probe(): bool
    {
        try {
            return $this->base(null)->get($this->config->url . '/')->status() === 200;
        } catch (ConnectionException $exception) {
            throw ConnectionFailed::during('GET /', (string) $this->config->url, $exception);
        }
    }

    /**
     * @param array{cookie: string, csrf: ?string, unifi_os: bool} $session
     * @param array<array-key, mixed>|null $payload
     */
    private function send(array $session, string $method, string $path, ?array $payload): Response
    {
        $request = $this->base($session);

        if ($method !== 'GET' && $session['csrf'] !== null) {
            $request = $request->withHeaders(['X-CSRF-Token' => $session['csrf']]);
        }

        $url = $this->config->url . ($session['unifi_os'] ? '/proxy/network' : '') . $path;

        return $request->send($method, $url, $payload === null ? [] : ['json' => $payload]);
    }

    /**
     * Keeps the CSRF token current when UniFi OS rotates it.
     */
    private function rememberCsrf(Response $response): void
    {
        $updated = trim($response->header('X-Updated-CSRF-Token'));

        if ($updated === '' || $this->session === null || $updated === $this->session['csrf']) {
            return;
        }

        $this->session['csrf'] = $updated;
        $this->cache->put($this->cacheKey(), $this->session, $this->sessionTtl);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $operation, Response $response): array
    {
        $payload = $response->json();

        if ($response->status() === 401 || $response->status() === 403) {
            throw AuthenticationException::fromPayload($operation, $response->status(), is_array($payload) ? $payload : []);
        }

        if ($response->status() === 429) {
            throw RateLimitException::fromResponse($operation, $response);
        }

        if (! is_array($payload)) {
            if ($response->successful() && trim($response->body()) === '') {
                return [];
            }

            throw ApiException::unexpected($operation, $response->status());
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : null;

        if (($meta !== null && ($meta['rc'] ?? null) === 'error') || $response->failed()) {
            throw ApiException::fromPayload($operation, $response->status(), $payload);
        }

        if ($meta !== null || array_key_exists('data', $payload)) {
            return is_array($payload['data'] ?? null) ? $payload['data'] : [];
        }

        return $payload;
    }

    /**
     * @param array{cookie: string, csrf: ?string, unifi_os: bool}|null $session
     */
    private function base(?array $session): PendingRequest
    {
        $request = $this->http
            ->createPendingRequest()
            ->acceptJson()
            ->asJson()
            ->withUserAgent('beziworld-laravel-unifi/1.0')
            ->withoutRedirecting()
            ->withOptions(['verify' => $this->config->verifySsl])
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);

        return $session === null ? $request : $request->withHeaders(['Cookie' => $session['cookie']]);
    }

    private function pause(int $attempt): void
    {
        if ($this->config->retryDelay > 0) {
            usleep($this->config->retryDelay * 1000 * $attempt);
        }
    }

    private function cacheKey(): string
    {
        return $this->cachePrefix . hash('sha256', implode('|', [$this->config->name, $this->config->url, $this->config->username]));
    }

    /**
     * Extracts name/value pairs from every Set-Cookie header.
     *
     * @return array<string, string>
     */
    private static function cookies(Response $response): array
    {
        $cookies = [];

        foreach ($response->headers() as $name => $values) {
            if (strtolower((string) $name) !== 'set-cookie') {
                continue;
            }

            foreach ((array) $values as $line) {
                $pair = explode(';', (string) $line, 2)[0];
                $parts = explode('=', $pair, 2);

                if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                    $cookies[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        return $cookies;
    }

    /**
     * Reads the "csrfToken" claim from the payload of the TOKEN JWT.
     */
    private static function csrfFromJwt(string $jwt): ?string
    {
        $segments = explode('.', $jwt);

        if (count($segments) < 2) {
            return null;
        }

        $json = base64_decode(strtr($segments[1], '-_', '+/'), true);
        $claims = is_string($json) ? json_decode($json, true) : null;

        return is_array($claims) && is_string($claims['csrfToken'] ?? null) && $claims['csrfToken'] !== '' ? $claims['csrfToken'] : null;
    }
}
