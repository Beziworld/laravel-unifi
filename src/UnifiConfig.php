<?php

declare(strict_types=1);

namespace Beziworld\Unifi;

use Beziworld\Unifi\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable settings of a single UniFi controller connection.
 */
final readonly class UnifiConfig
{
    public const string CLOUD_URL = 'https://api.ui.com';

    public const string CONTROLLER_AUTO = 'auto';

    public const string CONTROLLER_UNIFI_OS = 'unifi_os';

    public const string CONTROLLER_LEGACY = 'legacy';

    public function __construct(
        public string $name,
        public ?string $url,
        #[\SensitiveParameter]
        public ?string $apiKey = null,
        public ?string $username = null,
        #[\SensitiveParameter]
        public ?string $password = null,
        public string $site = 'default',
        public ?string $siteId = null,
        public ?string $consoleId = null,
        public string $controller = self::CONTROLLER_AUTO,
        public bool|string $verifySsl = true,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public int $retries = 2,
        public int $retryDelay = 300,
        public string $cloudUrl = self::CLOUD_URL,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfiguration When the address or every kind of credentials is missing.
     */
    public static function fromArray(array $config, string $name = 'default'): self
    {
        $consoleId = self::string($config['console_id'] ?? null);
        $url = self::string($config['url'] ?? null);

        if ($url === null && $consoleId === null) {
            throw InvalidConfiguration::missing('url', 'UNIFI_URL', $name);
        }

        if ($url !== null) {
            if (preg_match('#^https?://#i', $url) !== 1) {
                $url = 'https://' . $url;
            }

            $url = rtrim($url, '/');
        }

        $apiKey = self::string($config['api_key'] ?? null);
        $username = self::string($config['username'] ?? null);
        $password = is_string($config['password'] ?? null) && $config['password'] !== '' ? $config['password'] : null;

        if ($apiKey === null && ($username === null || $password === null)) {
            throw InvalidConfiguration::missing('api_key', 'UNIFI_API_KEY (or UNIFI_USERNAME and UNIFI_PASSWORD)', $name);
        }

        $controller = strtolower(self::string($config['controller'] ?? null) ?? self::CONTROLLER_AUTO);

        if (! in_array($controller, [self::CONTROLLER_AUTO, self::CONTROLLER_UNIFI_OS, self::CONTROLLER_LEGACY], true)) {
            throw InvalidConfiguration::invalid('controller', $controller, $name);
        }

        return new self(
            name: $name,
            url: $url,
            apiKey: $apiKey,
            username: $username,
            password: $password,
            site: self::string($config['site'] ?? null) ?? 'default',
            siteId: self::string($config['site_id'] ?? null),
            consoleId: $consoleId,
            controller: $controller,
            verifySsl: self::verify($config['verify_ssl'] ?? true),
            timeout: self::positiveInt($config['timeout'] ?? null, 30),
            connectTimeout: self::positiveInt($config['connect_timeout'] ?? null, 10),
            retries: self::naturalInt($config['retries'] ?? null, 2),
            retryDelay: self::naturalInt($config['retry_delay'] ?? null, 300),
            cloudUrl: rtrim(self::string($config['cloud_url'] ?? null) ?? self::CLOUD_URL, '/'),
        );
    }

    /**
     * Base URL of the official Network API (local console or cloud connector), without the "/v1" segment.
     */
    public function integrationUrl(): string
    {
        if ($this->consoleId !== null) {
            return sprintf('%s/v1/connector/consoles/%s/proxy/network/integration', $this->cloudUrl, rawurlencode($this->consoleId));
        }

        return $this->url . '/proxy/network/integration';
    }

    public function usesCloudConnector(): bool
    {
        return $this->consoleId !== null;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    public function hasCredentials(): bool
    {
        return $this->username !== null && $this->password !== null && $this->url !== null;
    }

    /**
     * Copy of the configuration bound to another site.
     *
     * @param string|null $siteId UUID for the official API.
     * @param string|null $site Short name for the classic API.
     */
    public function withSite(?string $siteId, ?string $site = null): self
    {
        return new self(
            $this->name,
            $this->url,
            $this->apiKey,
            $this->username,
            $this->password,
            $site ?? $this->site,
            $siteId,
            $this->consoleId,
            $this->controller,
            $this->verifySsl,
            $this->timeout,
            $this->connectTimeout,
            $this->retries,
            $this->retryDelay,
            $this->cloudUrl,
        );
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private static function verify(mixed $value): bool|string
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => trim($value),
            };
        }

        return true;
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private static function naturalInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : $default;
    }
}
