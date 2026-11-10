<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Device;
use Beziworld\Unifi\Data\DeviceStatistics;
use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Support\Filter;
use Illuminate\Support\LazyCollection;

/**
 * Adopted and pending UniFi devices of a site.
 */
final class Devices extends Resource
{
    /**
     * @return Page<Device>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page($this->sitePath('/devices'), Device::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, Device>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor($this->sitePath('/devices'), Device::fromArray(...), $filter, 200);
    }

    public function get(string $deviceId): Device
    {
        return Device::fromArray($this->api->get($this->sitePath('/devices' . self::segment($deviceId))));
    }

    public function statistics(string $deviceId): DeviceStatistics
    {
        return DeviceStatistics::fromArray($this->api->get($this->sitePath('/devices' . self::segment($deviceId) . '/statistics/latest')));
    }

    /**
     * Devices waiting for adoption on any site of the application (GET /v1/pending-devices).
     *
     * @return Page<Device>
     */
    public function pending(Filter|string|null $filter = null, int $offset = 0, int $limit = 25): Page
    {
        return $this->page('/v1/pending-devices', Device::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * Adopts a pending device into the site.
     */
    public function adopt(string $macAddress, bool $ignoreDeviceLimit = false): Device
    {
        return Device::fromArray($this->api->post($this->sitePath('/devices'), [
            'macAddress' => strtolower($macAddress),
            'ignoreDeviceLimit' => $ignoreDeviceLimit,
        ]));
    }

    /**
     * Removes (unadopts) a device from the site.
     */
    public function remove(string $deviceId): void
    {
        $this->api->delete($this->sitePath('/devices' . self::segment($deviceId)));
    }

    public function restart(string $deviceId): void
    {
        $this->api->post($this->sitePath('/devices' . self::segment($deviceId) . '/actions'), ['action' => 'RESTART']);
    }

    /**
     * Power-cycles the PoE output of a switch port, e.g. to reboot an access point or a camera.
     */
    public function powerCyclePort(string $deviceId, int $portIdx): void
    {
        $this->api->post($this->sitePath('/devices' . self::segment($deviceId) . '/interfaces/ports/' . $portIdx . '/actions'), ['action' => 'POWER_CYCLE']);
    }
}
