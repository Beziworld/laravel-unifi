<?php

declare(strict_types=1);

use Beziworld\Unifi\Data\Voucher;
use Beziworld\Unifi\Data\VoucherRequest;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Support\Filter;
use Beziworld\Unifi\UnifiConfig;
use Carbon\CarbonImmutable;

it('normalizes the controller url', function (string $url, string $expected): void {
    expect(UnifiConfig::fromArray(['url' => $url, 'api_key' => 'k'])->url)->toBe($expected);
})->with([
    ['192.168.1.1', 'https://192.168.1.1'],
    ['https://unifi.example.com:8443/', 'https://unifi.example.com:8443'],
    ['http://10.0.0.1', 'http://10.0.0.1'],
]);

it('builds the local and cloud integration urls', function (): void {
    expect(UnifiConfig::fromArray(['url' => '192.168.1.1', 'api_key' => 'k'])->integrationUrl())->toBe('https://192.168.1.1/proxy/network/integration')
        ->and(UnifiConfig::fromArray(['console_id' => 'ABC:1', 'api_key' => 'k'])->integrationUrl())->toBe('https://api.ui.com/v1/connector/consoles/ABC%3A1/proxy/network/integration');
});

it('requires an address and some credentials', function (array $config, string $key): void {
    expect(fn() => UnifiConfig::fromArray($config, 'hotel'))->toThrow(InvalidConfiguration::class, $key);
})->with([
    'no url' => [['api_key' => 'k'], '"url"'],
    'no credentials' => [['url' => 'h'], '"api_key"'],
    'username without password' => [['url' => 'h', 'username' => 'u'], '"api_key"'],
]);

it('accepts classic credentials without an api key', function (): void {
    $config = UnifiConfig::fromArray(['url' => 'h', 'username' => 'u', 'password' => 'p']);

    expect($config->hasApiKey())->toBeFalse()->and($config->hasCredentials())->toBeTrue();
});

it('rejects an unknown controller flavour', function (): void {
    expect(fn() => UnifiConfig::fromArray(['url' => 'h', 'api_key' => 'k', 'controller' => 'cloudkey']))->toThrow(InvalidConfiguration::class, 'controller');
});

it('parses the tls verification setting', function (mixed $value, bool|string $expected): void {
    expect(UnifiConfig::fromArray(['url' => 'h', 'api_key' => 'k', 'verify_ssl' => $value])->verifySsl)->toBe($expected);
})->with([
    [true, true],
    ['false', false],
    [0, false],
    [null, true],
    ['/etc/ssl/unifi-ca.pem', '/etc/ssl/unifi-ca.pem'],
]);

it('builds filter expressions with escaped literals', function (): void {
    expect((string) Filter::eq('name', "O'Brien"))->toBe("name.eq('O''Brien')")
        ->and((string) Filter::eq('id', '88F7AF54-98F8-306A-A1C7-C9349722B1F6'))->toBe('id.eq(88f7af54-98f8-306a-a1c7-c9349722b1f6)')
        ->and((string) Filter::and(Filter::isNull('name'), Filter::gt('createdAt', CarbonImmutable::parse('2025-01-01 02:00', 'Europe/Warsaw'))))->toBe('and(name.isNull(), createdAt.gt(2025-01-01T01:00:00Z))')
        ->and((string) Filter::not(Filter::like('name', 'guest*')))->toBe("not(name.like('guest*'))")
        ->and((string) Filter::in('timeLimitMinutes', [60, 120]))->toBe('timeLimitMinutes.in(60, 120)')
        ->and((string) Filter::eq('expired', true))->toBe('expired.eq(true)');
});

it('serializes voucher requests and validates the schema bounds', function (): void {
    expect((new VoucherRequest('Room 101', 1440, 2, null, 500))->toArray())
        ->toBe(['name' => 'Room 101', 'count' => 2, 'timeLimitMinutes' => 1440, 'dataUsageLimitMBytes' => 500])
        ->and(fn() => new VoucherRequest('x', 0))->toThrow(InvalidArgumentException::class, 'timeLimitMinutes')
        ->and(fn() => new VoucherRequest('x', 60, 1001))->toThrow(InvalidArgumentException::class, 'count')
        ->and(fn() => new VoucherRequest('x', 60, rxRateLimitKbps: 1))->toThrow(InvalidArgumentException::class, 'rxRateLimitKbps')
        ->and(fn() => new VoucherRequest(' ', 60))->toThrow(InvalidArgumentException::class, 'name');
});

it('computes a voucher lasting until check-out', function (): void {
    $now = CarbonImmutable::parse('2026-11-10 15:20:30', 'Europe/Warsaw');
    $request = VoucherRequest::untilCheckout('Room 7', $now->addDays(2)->setTime(11, 0), 3, now: $now);

    expect($request->timeLimitMinutes)->toBe(2 * 1440 - 260)
        ->and($request->authorizedGuestLimit)->toBe(3)
        ->and(fn() => VoucherRequest::untilCheckout('x', $now->subHour(), now: $now))->toThrow(InvalidArgumentException::class);
});

it('maps official voucher documents', function (): void {
    $voucher = Voucher::fromArray(jsonFixture('voucher'));

    expect($voucher->code)->toBe('4861409510')
        ->and($voucher->formattedCode())->toBe('48614-09510')
        ->and($voucher->createdAt?->toIso8601ZuluString())->toBe('2026-11-10T14:00:00Z')
        ->and($voucher->activatedAt)->toBeNull()
        ->and($voucher->isActivated())->toBeFalse()
        ->and($voucher->hasFreeSlots())->toBeTrue()
        ->and($voucher->dataUsageLimitMBytes)->toBe(1024);
});
