<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Support\Values;
use InvalidArgumentException;

/**
 * UniFi devices of the classic API (stat/device, cmd/devmgr).
 *
 * Device documents are returned as arrays because their shape differs by
 * device type and controller version.
 */
final class Devices extends ClassicResource
{
    /**
     * @param list<string> $macs Restricts the result to the given devices.
     * @return list<array<array-key, mixed>>
     */
    public function all(array $macs = []): array
    {
        return Values::rows($this->api->request('POST', $this->path('stat/device'), ['macs' => self::macs($macs)], idempotent: true));
    }

    /**
     * Compact device list: mac, state, adopted, disabled, type, model, name.
     *
     * @return list<array<array-key, mixed>>
     */
    public function basic(): array
    {
        return Values::rows($this->api->request('GET', $this->path('stat/device-basic'), idempotent: true));
    }

    /**
     * @param list<string> $macs
     * @param string $type "soft" restarts the device, "hard" also power-cycles PoE outputs of switches.
     */
    public function restart(array $macs, string $type = 'soft'): void
    {
        if (! in_array($type, ['soft', 'hard'], true)) {
            throw new InvalidArgumentException('The reboot type must be "soft" or "hard".');
        }

        $this->command('devmgr', 'restart', ['macs' => self::macs($macs), 'reboot_type' => $type]);
    }

    /**
     * @param list<string> $macs
     */
    public function adopt(array $macs): void
    {
        $this->command('devmgr', 'adopt', ['macs' => self::macs($macs)]);
    }

    /**
     * Starts or stops flashing the locating LED of a device.
     */
    public function locate(string $mac, bool $enabled = true): void
    {
        $this->command('devmgr', $enabled ? 'set-locate' : 'unset-locate', ['mac' => self::mac($mac)]);
    }
}
