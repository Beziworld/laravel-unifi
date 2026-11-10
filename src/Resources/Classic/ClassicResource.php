<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Http\ClassicApi;
use InvalidArgumentException;

/**
 * Base class of the classic API areas scoped to a site short name.
 */
abstract class ClassicResource
{
    public function __construct(
        protected readonly ClassicApi $api,
        protected readonly string $site,
    ) {}

    protected function path(string $path): string
    {
        return '/api/s/' . rawurlencode($this->site) . '/' . ltrim($path, '/');
    }

    /**
     * Sends a site command (cmd/stamgr, cmd/devmgr, cmd/hotspot, cmd/evtmgr) exactly once.
     *
     * @param array<string, mixed> $payload
     * @return array<array-key, mixed>
     */
    protected function command(string $manager, string $command, array $payload = []): array
    {
        return $this->api->request('POST', $this->path('cmd/' . $manager), ['cmd' => $command] + $payload);
    }

    protected static function mac(string $mac): string
    {
        $normalized = strtolower(str_replace('-', ':', trim($mac)));

        if (preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid MAC address.', $mac));
        }

        return $normalized;
    }

    /**
     * @param iterable<string> $macs
     * @return list<string>
     */
    protected static function macs(iterable $macs): array
    {
        $result = [];

        foreach ($macs as $mac) {
            $result[] = self::mac($mac);
        }

        return $result;
    }
}
