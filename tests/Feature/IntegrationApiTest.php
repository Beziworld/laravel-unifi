<?php

declare(strict_types=1);

use Beziworld\Unifi\Data\Device;
use Beziworld\Unifi\Data\VoucherRequest;
use Beziworld\Unifi\Exceptions\ApiException;
use Beziworld\Unifi\Exceptions\AuthenticationException;
use Beziworld\Unifi\Exceptions\ConnectionFailed;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Exceptions\RateLimitException;
use Beziworld\Unifi\Exceptions\SiteNotFound;
use Beziworld\Unifi\Facades\Unifi;
use Beziworld\Unifi\Support\Filter;
use Beziworld\Unifi\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends the api key to the local integration endpoint', function (): void {
    Http::fake([TestCase::API . '/v1/info' => Http::response(['applicationVersion' => '9.1.120'])]);

    $info = Unifi::info();

    expect($info->applicationVersion)->toBe('9.1.120')->and($info->versionAtLeast('9.0'))->toBeTrue();

    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
        && $request->url() === TestCase::API . '/v1/info'
        && $request->hasHeader('X-API-KEY', 'test-api-key')
        && $request->hasHeader('Accept', 'application/json'));
});

it('routes cloud connections through api.ui.com', function (): void {
    Http::fake(['https://api.ui.com/*' => Http::response(['applicationVersion' => '10.0.1'])]);

    Unifi::connection('cloud')->info();

    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://api.ui.com/v1/connector/consoles/CONSOLE%3A123/proxy/network/integration/v1/info'
        && $request->hasHeader('X-API-KEY', 'site-manager-key'));
});

it('lists sites with pagination and filters', function (): void {
    Http::fake(['*' => Http::response(TestCase::page([['id' => TestCase::SITE, 'internalReference' => 'default', 'name' => 'Default']], total: 3))]);

    $page = Unifi::sites()->list(Filter::eq('name', 'Default'), 0, 1);

    expect($page->items[0]->internalReference)->toBe('default')
        ->and($page->totalCount)->toBe(3)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->nextOffset())->toBe(1);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::API . '/v1/sites?offset=0&limit=1&filter=' . rawurlencode("name.eq('Default')"));
});

it('iterates over every page lazily', function (): void {
    Http::fake([
        TestCase::API . '/v1/sites/' . TestCase::SITE . '/devices?offset=0*' => Http::response(TestCase::page([jsonFixture('device'), jsonFixture('device')], 0, 3, 2)),
        TestCase::API . '/v1/sites/' . TestCase::SITE . '/devices?offset=2*' => Http::response(TestCase::page([jsonFixture('device')], 2, 3, 2)),
    ]);

    $devices = Unifi::devices()->all()->all();

    expect($devices)->toHaveCount(3)->and($devices[0])->toBeInstanceOf(Device::class);
    Http::assertSentCount(2);
});

it('resolves the site uuid from the short name once', function (): void {
    config()->set('unifi.connections.default.site_id', null);
    config()->set('unifi.connections.default.site', 'hotel');

    Http::fake([
        TestCase::API . '/v1/sites?*' => Http::response(TestCase::page([['id' => 'b7c3d1e2-0000-4000-8000-000000000001', 'internalReference' => 'hotel', 'name' => 'Hotel']])),
        TestCase::API . '/v1/sites/b7c3d1e2-0000-4000-8000-000000000001/*' => Http::response(TestCase::page([])),
    ]);

    Unifi::clients()->list();
    Unifi::vouchers()->list();

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), "internalReference.eq('hotel')"));
    Http::assertSentCount(3);
});

it('reports an unknown site reference', function (): void {
    config()->set('unifi.connections.default.site_id', null);
    config()->set('unifi.connections.default.site', 'missing');
    Http::fake(['*' => Http::response(TestCase::page([]))]);

    expect(fn() => Unifi::devices()->list())->toThrow(SiteNotFound::class, '"missing"');
});

it('switches sites with forSite', function (): void {
    Http::fake(['*' => Http::response(TestCase::page([]))]);

    Unifi::forSite('11111111-2222-4333-8444-555555555555')->networks()->list();

    Http::assertSent(fn(Request $request): bool => str_starts_with($request->url(), TestCase::API . '/v1/sites/11111111-2222-4333-8444-555555555555/networks'));
});

it('maps device details and statistics', function (): void {
    Http::fake([
        '*/statistics/latest' => Http::response(['uptimeSec' => 86400, 'cpuUtilizationPct' => 12.5, 'memoryUtilizationPct' => 40.1, 'lastHeartbeatAt' => '2026-11-10T14:00:00Z', 'interfaces' => []]),
        '*' => Http::response(jsonFixture('device')),
    ]);

    $device = Unifi::devices()->get('4a0c3f2e-1b5d-4c6e-9f7a-8b9c0d1e2f3a');
    $stats = Unifi::devices()->statistics('4a0c3f2e-1b5d-4c6e-9f7a-8b9c0d1e2f3a');

    expect($device->macAddress)->toBe('94:2a:6f:26:c6:ca')
        ->and($device->isOnline())->toBeTrue()
        ->and($device->isAccessPoint())->toBeTrue()
        ->and($device->isGateway())->toBeFalse()
        ->and($stats->uptimeSeconds)->toBe(86400)
        ->and($stats->cpuUtilizationPct)->toBe(12.5)
        ->and($stats->lastHeartbeatAt?->toIso8601ZuluString())->toBe('2026-11-10T14:00:00Z');
});

it('sends device and port actions', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    Unifi::devices()->restart('dev-1');
    Unifi::devices()->powerCyclePort('dev-1', 4);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->url() === TestCase::API . '/v1/sites/' . TestCase::SITE . '/devices/dev-1/actions'
        && $request->data() === ['action' => 'RESTART']);
    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::API . '/v1/sites/' . TestCase::SITE . '/devices/dev-1/interfaces/ports/4/actions'
        && $request->data() === ['action' => 'POWER_CYCLE']);
});

it('adopts a pending device', function (): void {
    Http::fake(['*' => Http::response(jsonFixture('device'))]);

    Unifi::devices()->adopt('94:2A:6F:26:C6:CA');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->data() === ['macAddress' => '94:2a:6f:26:c6:ca', 'ignoreDeviceLimit' => false]);
});

it('authorizes a guest client with limits', function (): void {
    Http::fake(['*' => Http::response(jsonFixture('guest-authorized'))]);

    $result = Unifi::clients()->authorizeGuest('497f6eca-6276-4993-bfeb-53cbbbba6f08', minutes: 1440, megabytes: 1024, downKbps: 1000, upKbps: 1000);

    expect($result->granted?->method)->toBe('API')
        ->and($result->granted?->expiresAt?->toIso8601ZuluString())->toBe('2026-11-11T14:00:00Z')
        ->and($result->revoked)->toBeNull();

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::API . '/v1/sites/' . TestCase::SITE . '/clients/497f6eca-6276-4993-bfeb-53cbbbba6f08/actions'
        && $request->data() === ['action' => 'AUTHORIZE_GUEST_ACCESS', 'timeLimitMinutes' => 1440, 'dataUsageLimitMBytes' => 1024, 'rxRateLimitKbps' => 1000, 'txRateLimitKbps' => 1000]);
});

it('validates guest authorization limits before sending', function (): void {
    expect(fn() => Unifi::clients()->authorizeGuest('c', downKbps: 1))->toThrow(InvalidArgumentException::class, 'downKbps');
    Http::assertNothingSent();
});

it('revokes guest access', function (): void {
    Http::fake(['*' => Http::response(['action' => 'UNAUTHORIZE_GUEST_ACCESS', 'revokedAuthorization' => jsonFixture('guest-authorized')['grantedAuthorization']])]);

    $result = Unifi::clients()->unauthorizeGuest('c1');

    expect($result->revoked?->method)->toBe('API');
    Http::assertSent(fn(Request $request): bool => $request->data() === ['action' => 'UNAUTHORIZE_GUEST_ACCESS']);
});

it('finds guest clients and maps their access state', function (): void {
    Http::fake(['*' => Http::response(TestCase::page([jsonFixture('client')]))]);

    $guests = Unifi::clients()->guests()->all();

    expect($guests[0]->isGuest())->toBeTrue()
        ->and($guests[0]->isAuthorizedGuest())->toBeFalse()
        ->and($guests[0]->macAddress)->toBe('aa:bb:cc:dd:ee:01')
        ->and($guests[0]->isWireless())->toBeTrue();

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), "filter=access.type.eq('GUEST')"));
});

it('generates vouchers', function (): void {
    Http::fake(['*' => Http::response(['vouchers' => [jsonFixture('voucher'), jsonFixture('voucher')]], 201)]);

    $vouchers = Unifi::vouchers()->create(new VoucherRequest('hotel-guest', 1440, 2, 1, 1024, 1000, 1000));

    expect($vouchers)->toHaveCount(2)->and($vouchers[0]->code)->toBe('4861409510');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->url() === TestCase::API . '/v1/sites/' . TestCase::SITE . '/hotspot/vouchers'
        && $request->data() === ['name' => 'hotel-guest', 'count' => 2, 'timeLimitMinutes' => 1440, 'authorizedGuestLimit' => 1, 'dataUsageLimitMBytes' => 1024, 'rxRateLimitKbps' => 1000, 'txRateLimitKbps' => 1000]);
});

it('generates a voucher for a hotel stay', function (): void {
    Http::fake(['*' => Http::response(['vouchers' => [jsonFixture('voucher')]], 201)]);

    $voucher = Unifi::vouchers()->forStay('Room 12', now()->addDay(), 2);

    expect($voucher->name)->toBe('hotel-guest');
    Http::assertSent(fn(Request $request): bool => ($request->data()['authorizedGuestLimit'] ?? null) === 2
        && in_array($request->data()['timeLimitMinutes'] ?? null, [1440, 1441], true));
});

it('finds vouchers by code and deletes them by filter', function (): void {
    Http::fake([
        TestCase::API . '/v1/sites/' . TestCase::SITE . '/hotspot/vouchers?offset*' => Http::response(TestCase::page([jsonFixture('voucher')])),
        TestCase::API . '/v1/sites/' . TestCase::SITE . '/hotspot/vouchers?filter*' => Http::response(['vouchersDeleted' => 7]),
    ]);

    expect(Unifi::vouchers()->findByCode('48614-09510')?->id)->toBe('6a2b1f0e-3c4d-4e5f-8a9b-0c1d2e3f4a5b')
        ->and(Unifi::vouchers()->purgeExpired())->toBe(7);

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), "code.eq('4861409510')"));
    Http::assertSent(fn(Request $request): bool => $request->method() === 'DELETE' && str_contains(urldecode($request->url()), 'filter=expired.eq(true)'));
});

it('replaces the full wifi definition when toggling an ssid', function (): void {
    Http::fake(function (Request $request) {
        return $request->method() === 'GET'
            ? Http::response(['id' => 'w1', 'type' => 'STANDARD', 'name' => 'Hotel Guest', 'enabled' => true, 'metadata' => ['origin' => 'USER_DEFINED'], 'securityConfiguration' => ['type' => 'OPEN'], 'hotspotConfiguration' => ['type' => 'CAPTIVE_PORTAL']])
            : Http::response(['id' => 'w1', 'type' => 'STANDARD', 'name' => 'Hotel Guest', 'enabled' => false, 'securityConfiguration' => ['type' => 'OPEN']]);
    });

    $wifi = Unifi::wifi()->disable('w1');

    expect($wifi->enabled)->toBeFalse()->and($wifi->securityType())->toBe('OPEN');
    Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT'
        && $request->data() === ['type' => 'STANDARD', 'name' => 'Hotel Guest', 'enabled' => false, 'securityConfiguration' => ['type' => 'OPEN'], 'hotspotConfiguration' => ['type' => 'CAPTIVE_PORTAL']]);
});

it('lists networks and wans', function (): void {
    Http::fake([
        '*/wans*' => Http::response(TestCase::page([['id' => 'wan-1', 'name' => 'Internet 1']])),
        '*' => Http::response(TestCase::page([['id' => 'n1', 'name' => 'Guests', 'management' => 'GATEWAY', 'enabled' => true, 'default' => false, 'vlanId' => 20, 'metadata' => []]])),
    ]);

    expect(Unifi::networks()->list()->items[0]->vlanId)->toBe(20)
        ->and(Unifi::networks()->wans())->toBe(['wan-1' => 'Internet 1']);
});

it('passes unwrapped endpoints through with the site placeholder', function (): void {
    Http::fake(['*' => Http::response(TestCase::page([]))]);

    Unifi::request('GET', '/v1/sites/{siteId}/firewall/zones', ['limit' => 10]);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::API . '/v1/sites/' . TestCase::SITE . '/firewall/zones?limit=10');
});

it('raises api errors with the official error code', function (): void {
    Http::fake(['*' => Http::response(jsonFixture('error'), 400)]);

    try {
        Unifi::sites()->list();
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe('api.request.argument-type-mismatch')
            ->and($exception->httpStatus())->toBe(400)
            ->and($exception->requestId())->toBe('3fa85f64-5717-4562-b3fc-2c963f66afa6')
            ->and($exception->getMessage())->toContain('Invalid value of parameter siteId');
    }
});

it('raises authentication errors for rejected keys, also from the cloud connector', function (): void {
    Http::fake(['*' => Http::response(['code' => 'unauthorized', 'httpStatusCode' => 401, 'message' => 'unauthorized', 'traceId' => 'abc'], 401)]);

    try {
        Unifi::connection('cloud')->info();
        test()->fail('Expected exception was not thrown.');
    } catch (AuthenticationException $exception) {
        expect($exception->requestId())->toBe('abc')->and($exception->httpStatus())->toBe(401);
    }

    Http::assertSentCount(1);
});

it('reports rate limits with retry-after', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Too many requests'], 429, ['Retry-After' => '30'])]);

    try {
        Unifi::info();
        test()->fail('Expected exception was not thrown.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter())->toBe(30);
    }
});

it('retries reads on server errors', function (): void {
    Http::fakeSequence()->push('', 502)->push(['applicationVersion' => '9.1.0']);

    expect(Unifi::info()->applicationVersion)->toBe('9.1.0');
    Http::assertSentCount(2);
});

it('never retries writes', function (): void {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn() => Unifi::vouchers()->generate('x', 60))->toThrow(ApiException::class, 'HTTP 503');
    Http::assertSentCount(1);
});

it('wraps connection errors after exhausting read retries', function (): void {
    Http::fake(fn() => throw new ConnectionException('Connection refused'));

    expect(fn() => Unifi::info())->toThrow(ConnectionFailed::class, 'Connection refused');
    Http::assertSentCount(0);
});

it('requires an api key for the official api', function (): void {
    expect(fn() => Unifi::connection('legacy')->info())->toThrow(InvalidConfiguration::class, 'UNIFI_API_KEY');
});

it('creates clients for runtime credentials', function (): void {
    Http::fake(['*' => Http::response(['applicationVersion' => '9.0.0'])]);

    Unifi::using(['url' => 'https://10.0.0.1', 'api_key' => 'tenant-key', 'site_id' => TestCase::SITE])->info();

    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://10.0.0.1/proxy/network/integration/v1/info' && $request->hasHeader('X-API-KEY', 'tenant-key'));
});

it('rejects unknown connections', function (): void {
    expect(fn() => Unifi::connection('nope'))->toThrow(InvalidConfiguration::class, '"nope"');
});
