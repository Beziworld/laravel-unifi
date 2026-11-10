<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Facades;

use Beziworld\Unifi\UnifiManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\Unifi\UnifiClient connection(?string $name = null)
 * @method static \Beziworld\Unifi\UnifiClient using(\Beziworld\Unifi\UnifiConfig|array<string, mixed> $config, string $name = 'runtime')
 * @method static string getDefaultConnection()
 * @method static list<string> connectionNames()
 * @method static void purge(?string $name = null)
 * @method static \Beziworld\Unifi\UnifiClient forSite(string $site)
 * @method static string siteId()
 * @method static \Beziworld\Unifi\UnifiConfig config()
 * @method static \Beziworld\Unifi\Data\ApplicationInfo info()
 * @method static \Beziworld\Unifi\Resources\Sites sites()
 * @method static \Beziworld\Unifi\Resources\Devices devices()
 * @method static \Beziworld\Unifi\Resources\Clients clients()
 * @method static \Beziworld\Unifi\Resources\Vouchers vouchers()
 * @method static \Beziworld\Unifi\Resources\Networks networks()
 * @method static \Beziworld\Unifi\Resources\WifiBroadcasts wifi()
 * @method static \Beziworld\Unifi\Classic classic(?string $site = null)
 * @method static array<array-key, mixed> request(string $method, string $path, array<string, scalar|null> $query = [], array<array-key, mixed>|null $body = null)
 * @method static \Beziworld\Unifi\Http\IntegrationApi integration()
 *
 * @see UnifiManager
 * @see \Beziworld\Unifi\UnifiClient
 */
final class Unifi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UnifiManager::class;
    }
}
