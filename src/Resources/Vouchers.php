<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources;

use Beziworld\Unifi\Data\Page;
use Beziworld\Unifi\Data\Voucher;
use Beziworld\Unifi\Data\VoucherRequest;
use Beziworld\Unifi\Exceptions\ApiException;
use Beziworld\Unifi\Support\Filter;
use Beziworld\Unifi\Support\Values;
use DateTimeInterface;
use Illuminate\Support\LazyCollection;

/**
 * Hotspot vouchers of a site (official API).
 */
final class Vouchers extends Resource
{
    /**
     * @param Filter|string|null $filter Filterable: id, createdAt, name, code, expired, expiresAt, activatedAt, ...
     * @return Page<Voucher>
     */
    public function list(Filter|string|null $filter = null, int $offset = 0, int $limit = 100): Page
    {
        return $this->page($this->sitePath('/hotspot/vouchers'), Voucher::fromArray(...), $filter, $offset, $limit);
    }

    /**
     * @return LazyCollection<int, Voucher>
     */
    public function all(Filter|string|null $filter = null): LazyCollection
    {
        return $this->cursor($this->sitePath('/hotspot/vouchers'), Voucher::fromArray(...), $filter, 1000);
    }

    public function get(string $voucherId): Voucher
    {
        return Voucher::fromArray($this->api->get($this->sitePath('/hotspot/vouchers' . self::segment($voucherId))));
    }

    /**
     * Finds a voucher by its code; dashes and spaces are ignored ("12345-67890").
     */
    public function findByCode(string $code): ?Voucher
    {
        return $this->list(Filter::eq('code', preg_replace('/[^0-9A-Za-z]/', '', $code) ?? $code), 0, 1)->first();
    }

    /**
     * Generates a batch of vouchers.
     *
     * @return list<Voucher>
     */
    public function create(VoucherRequest $request): array
    {
        $response = $this->api->post($this->sitePath('/hotspot/vouchers'), $request->toArray());

        return array_map(Voucher::fromArray(...), Values::rows($response['vouchers'] ?? []));
    }

    /**
     * Generates vouchers with the most common options.
     *
     * @param int|null $devices Devices allowed per voucher (null: unlimited).
     * @return list<Voucher>
     */
    public function generate(string $name, int $minutes, int $count = 1, ?int $devices = 1, ?int $megabytes = null, ?int $downKbps = null, ?int $upKbps = null): array
    {
        return $this->create(new VoucherRequest($name, $minutes, $count, $devices, $megabytes, $downKbps, $upKbps));
    }

    /**
     * Generates a single voucher valid until the guest's check-out, e.g. for a hotel stay.
     */
    public function forStay(string $name, DateTimeInterface $checkout, ?int $devices = 1): Voucher
    {
        $vouchers = $this->create(VoucherRequest::untilCheckout($name, $checkout, $devices));

        return $vouchers[0] ?? throw new ApiException('UniFi did not return the generated voucher.');
    }

    public function delete(string $voucherId): void
    {
        $this->api->delete($this->sitePath('/hotspot/vouchers' . self::segment($voucherId)));
    }

    /**
     * Deletes every voucher matching the filter and returns the number of deleted vouchers.
     */
    public function deleteWhere(Filter|string $filter): int
    {
        return Values::int($this->api->delete($this->sitePath('/hotspot/vouchers'), ['filter' => (string) $filter]), 'vouchersDeleted');
    }

    /**
     * Deletes every expired voucher of the site.
     */
    public function purgeExpired(): int
    {
        return $this->deleteWhere(Filter::eq('expired', true));
    }
}
