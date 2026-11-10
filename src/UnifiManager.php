<?php

declare(strict_types=1);

namespace Beziworld\Unifi;

use BadMethodCallException;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Http\ClassicApi;
use Beziworld\Unifi\Http\IntegrationApi;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Resolves clients of the UniFi controllers defined in config/unifi.php.
 *
 * Named connections work like database connections. Controllers stored at
 * runtime (for example per hotel or per customer in the database) are
 * reached with using(). Calls not defined on the manager are forwarded to
 * the default connection, so Unifi::vouchers() and
 * Unifi::connection('hotel2')->vouchers() are both valid.
 *
 * @mixin UnifiClient
 */
class UnifiManager
{
    /** @var array<string, UnifiClient> */
    private array $clients = [];

    public function __construct(
        private readonly Config $config,
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
    ) {}

    /**
     * @throws InvalidConfiguration When the connection is not defined or incomplete.
     */
    public function connection(?string $name = null): UnifiClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= $this->resolve($name);
    }

    /**
     * Client for a controller that is not stored in the configuration file.
     *
     * @param UnifiConfig|array<string, mixed> $config Keys as in a connection: url, api_key, username, password, site, ...
     */
    public function using(UnifiConfig|array $config, string $name = 'runtime'): UnifiClient
    {
        return $this->make($config instanceof UnifiConfig ? $config : UnifiConfig::fromArray($config, $name));
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config->get('unifi.default');

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
     * @return list<string>
     */
    public function connectionNames(): array
    {
        $connections = $this->config->get('unifi.connections');

        return is_array($connections) ? array_map(strval(...), array_keys($connections)) : [];
    }

    /**
     * Forgets resolved clients, e.g. after the configuration changed.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->connection(), $method];

        if (! is_callable($callable)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', UnifiClient::class, $method));
        }

        return $callable(...$arguments);
    }

    private function resolve(string $name): UnifiClient
    {
        $config = $this->config->get('unifi.connections.' . $name);

        if (! is_array($config)) {
            throw InvalidConfiguration::unknownConnection($name);
        }

        /** @var array<string, mixed> $config */
        return $this->make(UnifiConfig::fromArray($config, $name));
    }

    private function make(UnifiConfig $config): UnifiClient
    {
        $store = $this->config->get('unifi.session.store');
        $prefix = $this->config->get('unifi.session.prefix');
        $ttl = $this->config->get('unifi.session.ttl');

        return new UnifiClient(
            new IntegrationApi($this->http, $config),
            new ClassicApi(
                $this->http,
                $config,
                $this->cache->store(is_string($store) && $store !== '' ? $store : null),
                is_string($prefix) && $prefix !== '' ? $prefix : 'unifi:session:',
                is_numeric($ttl) && (int) $ttl > 0 ? (int) $ttl : 3600,
            ),
        );
    }
}
