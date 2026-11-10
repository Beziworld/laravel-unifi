<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Console;

use Beziworld\Unifi\Data\Voucher;
use Beziworld\Unifi\Data\VoucherRequest;
use Beziworld\Unifi\Exceptions\UnifiException;
use Beziworld\Unifi\UnifiManager;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Generates hotspot vouchers through the official API and prints their codes.
 */
final class CreateVouchersCommand extends Command
{
    /** @var string */
    protected $signature = 'unifi:vouchers
        {name : Voucher name (note), e.g. a room number}
        {--count=1 : Number of vouchers}
        {--minutes=1440 : Validity after activation in minutes}
        {--guests=1 : Devices per voucher (0: unlimited)}
        {--megabytes= : Data limit in MB}
        {--connection= : Connection name from config/unifi.php}
        {--site= : Site UUID or short name}';

    /** @var string */
    protected $description = 'Generate UniFi hotspot vouchers';

    public function handle(UnifiManager $manager): int
    {
        try {
            $request = new VoucherRequest(
                name: self::stringInput($this->input->getArgument('name')) ?? '',
                timeLimitMinutes: self::intInput($this->input->getOption('minutes')) ?? 1440,
                count: self::intInput($this->input->getOption('count')) ?? 1,
                authorizedGuestLimit: (self::intInput($this->input->getOption('guests')) ?? 1) === 0 ? null : (self::intInput($this->input->getOption('guests')) ?? 1),
                dataUsageLimitMBytes: self::intInput($this->input->getOption('megabytes')),
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        try {
            $client = $manager->connection(self::stringInput($this->input->getOption('connection')));
            $site = self::stringInput($this->input->getOption('site'));

            if ($site !== null) {
                $client = $client->forSite($site);
            }

            $vouchers = $client->vouchers()->create($request);
        } catch (UnifiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Code', 'Name', 'Minutes', 'Devices'], array_map(static fn(Voucher $voucher): array => [
            $voucher->formattedCode(),
            $voucher->name,
            (string) $voucher->timeLimitMinutes,
            $voucher->authorizedGuestLimit === null ? 'unlimited' : (string) $voucher->authorizedGuestLimit,
        ], $vouchers));

        return self::SUCCESS;
    }

    private static function stringInput(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function intInput(mixed $value): ?int
    {
        return is_string($value) && is_numeric($value) ? (int) $value : (is_int($value) ? $value : null);
    }
}
