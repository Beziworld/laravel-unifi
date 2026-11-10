<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Data\ClassicVoucher;
use Beziworld\Unifi\Support\Values;
use InvalidArgumentException;

/**
 * Hotspot vouchers of the classic API (stat/voucher, cmd/hotspot).
 */
final class Vouchers extends ClassicResource
{
    /**
     * @param int|null $createTime Restricts the list to one batch (the create_time returned by create()).
     * @return list<ClassicVoucher>
     */
    public function list(?int $createTime = null): array
    {
        return array_map(ClassicVoucher::fromArray(...), Values::rows($this->api->request(
            'POST',
            $this->path('stat/voucher'),
            $createTime === null ? [] : ['create_time' => $createTime],
            idempotent: true,
        )));
    }

    /**
     * Creates a batch of vouchers and returns them.
     *
     * The controller answers the creation with the batch timestamp only, so
     * the vouchers are read back with a second (read-only) request.
     *
     * @param int $minutes Validity after activation.
     * @param int $quota 0: unlimited uses, 1: single use, n: n uses.
     * @param int|null $megabytes Data transfer limit in MB.
     * @return list<ClassicVoucher>
     */
    public function create(int $minutes, int $count = 1, int $quota = 1, ?string $note = null, ?int $upKbps = null, ?int $downKbps = null, ?int $megabytes = null): array
    {
        if ($minutes < 1 || $count < 1 || $quota < 0) {
            throw new InvalidArgumentException('Voucher minutes and count must be positive and the quota must not be negative.');
        }

        $result = $this->command('hotspot', 'create-voucher', array_filter([
            'expire' => $minutes,
            'n' => $count,
            'quota' => $quota,
            'note' => $note === null || trim($note) === '' ? null : trim($note),
            'up' => $upKbps,
            'down' => $downKbps,
            'bytes' => $megabytes,
        ], static fn(int|string|null $value): bool => $value !== null));

        $createTime = Values::nullableInt(Values::rows($result)[0] ?? [], 'create_time');

        return $createTime === null ? [] : $this->list($createTime);
    }

    public function revoke(string $voucherId): void
    {
        $this->command('hotspot', 'delete-voucher', ['_id' => $voucherId]);
    }
}
