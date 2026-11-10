<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Tests;

use Beziworld\Unifi\Facades\Unifi;
use Beziworld\Unifi\UnifiManager;
use Beziworld\Unifi\UnifiServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string URL = 'https://192.168.1.1';

    public const string API = 'https://192.168.1.1/proxy/network/integration';

    public const string SITE = '88f7af54-98f8-306a-a1c7-c9349722b1f6';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        app()->forgetInstance(UnifiManager::class);
        Unifi::clearResolvedInstances();
    }

    protected function getPackageProviders($app): array
    {
        return [UnifiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('unifi.connections.default', [
            'url' => '192.168.1.1',
            'api_key' => 'test-api-key',
            'username' => 'api-user',
            'password' => 'secret',
            'site' => 'default',
            'site_id' => self::SITE,
            'controller' => 'unifi_os',
            'verify_ssl' => false,
            'retry_delay' => 0,
        ]);
        $app['config']->set('unifi.connections.legacy', [
            'url' => 'https://unifi.example.com:8443',
            'username' => 'admin',
            'password' => 'secret',
            'site' => 'hotel',
            'controller' => 'auto',
            'retry_delay' => 0,
        ]);
        $app['config']->set('unifi.connections.cloud', [
            'console_id' => 'CONSOLE:123',
            'api_key' => 'site-manager-key',
            'retry_delay' => 0,
        ]);
    }

    /**
     * Builds an unsigned JWT carrying the given claims, as used in the UniFi OS TOKEN cookie.
     *
     * @param array<string, mixed> $claims
     */
    public static function jwt(array $claims): string
    {
        $encode = static fn(string $json): string => rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $encode('{"alg":"HS256","typ":"JWT"}') . '.' . $encode((string) json_encode($claims)) . '.signature';
    }

    /**
     * @param list<array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function page(array $data, int $offset = 0, ?int $total = null, int $limit = 25): array
    {
        return ['offset' => $offset, 'limit' => $limit, 'count' => count($data), 'totalCount' => $total ?? count($data), 'data' => $data];
    }
}
