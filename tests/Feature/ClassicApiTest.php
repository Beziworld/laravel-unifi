<?php

declare(strict_types=1);

use Beziworld\Unifi\Exceptions\ApiException;
use Beziworld\Unifi\Exceptions\AuthenticationException;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Facades\Unifi;
use Beziworld\Unifi\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function unifiOsLogin(string $csrf = 'csrf-from-header'): array
{
    return [TestCase::URL . '/api/auth/login' => Http::response(['unique_id' => 'u1'], 200, [
        'Set-Cookie' => 'TOKEN=' . TestCase::jwt(['csrfToken' => 'csrf-from-jwt']) . '; path=/; secure; httponly',
        'X-CSRF-Token' => $csrf,
    ])];
}

it('logs in to unifi os and proxies network calls with the session cookie', function (): void {
    Http::fake(unifiOsLogin() + [TestCase::URL . '/proxy/network/api/s/default/stat/sta' => Http::response(jsonFixture('classic-clients'))]);

    $clients = Unifi::classic()->clients()->online();

    expect($clients[0]->mac)->toBe('aa:bb:cc:dd:ee:01')
        ->and($clients[0]->isGuest)->toBeTrue()
        ->and($clients[0]->authorized)->toBeTrue()
        ->and($clients[0]->displayName())->toBe('guest-phone')
        ->and($clients[0]->lastSeen?->getTimestamp())->toBe(1794319200);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '/api/auth/login'
        && $request->method() === 'POST'
        && ($request->data()['username'] ?? null) === 'api-user'
        && ($request->data()['password'] ?? null) === 'secret');
    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '/proxy/network/api/s/default/stat/sta'
        && str_starts_with($request->header('Cookie')[0] ?? '', 'TOKEN=')
        && ! $request->hasHeader('X-CSRF-Token'));
});

it('sends the csrf token with state-changing commands', function (): void {
    Http::fake(unifiOsLogin() + ['*/cmd/stamgr' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->clients()->authorizeGuest('AA-BB-CC-DD-EE-01', 1440, upKbps: 512, downKbps: 2048, megabytes: 500, apMac: '94:2A:6F:26:C6:CA');

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '/proxy/network/api/s/default/cmd/stamgr'
        && $request->hasHeader('X-CSRF-Token', 'csrf-from-header')
        && $request->data() === ['cmd' => 'authorize-guest', 'mac' => 'aa:bb:cc:dd:ee:01', 'minutes' => 1440, 'up' => 512, 'down' => 2048, 'bytes' => 500, 'ap_mac' => '94:2a:6f:26:c6:ca']);
});

it('falls back to the csrf claim of the token', function (): void {
    Http::fake([
        TestCase::URL . '/api/auth/login' => Http::response([], 200, ['Set-Cookie' => 'TOKEN=' . TestCase::jwt(['csrfToken' => 'csrf-from-jwt']) . '; path=/']),
        '*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []]),
    ]);

    Unifi::classic()->clients()->block('aa:bb:cc:dd:ee:01');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/cmd/stamgr') && $request->hasHeader('X-CSRF-Token', 'csrf-from-jwt'));
});

it('reuses the cached session across clients', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->sites()->all();
    Unifi::purge();
    Unifi::classic()->sites()->stats();

    Http::assertSentCount(3);
    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '/proxy/network/api/stat/sites');
});

it('renews an expired session once', function (): void {
    $calls = 0;

    Http::fake(unifiOsLogin() + ['*/stat/device-basic' => function () use (&$calls) {
        return ++$calls === 1 ? Http::response(['meta' => ['rc' => 'error', 'msg' => 'api.err.LoginRequired']], 401) : Http::response(['meta' => ['rc' => 'ok'], 'data' => [['mac' => 'x']]]);
    }]);

    Cache::put('unifi:session:' . hash('sha256', 'default|https://192.168.1.1|api-user'), ['cookie' => 'TOKEN=old', 'csrf' => 'old', 'unifi_os' => true], 60);

    expect(Unifi::classic()->devices()->basic())->toBe([['mac' => 'x']]);

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/api/auth/login'));
    Http::assertSentCount(3);
});

it('does not loop when the renewed session is rejected too', function (): void {
    Http::fake(unifiOsLogin() + ['*/stat/sta' => Http::response(['meta' => ['rc' => 'error', 'msg' => 'api.err.LoginRequired']], 401)]);

    expect(fn() => Unifi::classic()->clients()->online())->toThrow(AuthenticationException::class, 'api.err.LoginRequired');
    Http::assertSentCount(4);
});

it('raises an authentication error for rejected logins without retrying', function (): void {
    Http::fake([TestCase::URL . '/api/auth/login' => Http::response(['code' => 'AUTHENTICATION_FAILED_INVALID_CREDENTIALS', 'message' => 'Invalid username or password'], 401)]);

    expect(fn() => Unifi::classic()->sites()->all())->toThrow(AuthenticationException::class, 'Invalid username or password');
    Http::assertSentCount(1);
});

it('detects legacy controllers and uses the unifises cookie without the proxy prefix', function (): void {
    Http::fake([
        'https://unifi.example.com:8443/' => Http::response('', 302, ['Location' => '/manage']),
        'https://unifi.example.com:8443/api/login' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []], 200, ['Set-Cookie' => 'unifises=abc123; Path=/; Secure; HttpOnly']),
        'https://unifi.example.com:8443/api/s/hotel/stat/voucher' => Http::response(jsonFixture('classic-vouchers')),
    ]);

    $vouchers = Unifi::connection('legacy')->classic()->vouchers()->list();

    expect($vouchers[0]->formattedCode())->toBe('12345-67890')
        ->and($vouchers[0]->durationMinutes)->toBe(1440)
        ->and($vouchers[0]->megabytes)->toBe(500)
        ->and($vouchers[0]->note)->toBe('Room 101');

    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://unifi.example.com:8443/api/s/hotel/stat/voucher'
        && $request->hasHeader('Cookie', 'unifises=abc123'));
});

it('creates classic vouchers and reads the batch back', function (): void {
    Http::fake(unifiOsLogin() + [
        '*/cmd/hotspot' => Http::response(['meta' => ['rc' => 'ok'], 'data' => [['create_time' => 1794319200]]]),
        '*/stat/voucher' => Http::response(jsonFixture('classic-vouchers')),
    ]);

    $vouchers = Unifi::classic('hotel2')->vouchers()->create(1440, 1, 1, 'Room 101', 512, 2048, 500);

    expect($vouchers[0]->code)->toBe('1234567890');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/api/s/hotel2/cmd/hotspot')
        && $request->data() === ['cmd' => 'create-voucher', 'expire' => 1440, 'n' => 1, 'quota' => 1, 'note' => 'Room 101', 'up' => 512, 'down' => 2048, 'bytes' => 500]);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/stat/voucher') && $request->data() === ['create_time' => 1794319200]);
});

it('revokes classic vouchers', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->vouchers()->revoke('5f8d0d55b54764421b7156c9');

    Http::assertSent(fn(Request $request): bool => $request->data() === ['cmd' => 'delete-voucher', '_id' => '5f8d0d55b54764421b7156c9']);
});

it('reads events and alarms', function (): void {
    Http::fake(unifiOsLogin() + [
        '*/stat/event' => Http::response(jsonFixture('classic-events')),
        '*/list/alarm' => Http::response(['meta' => ['rc' => 'ok'], 'data' => [['_id' => 'a1', 'key' => 'EVT_GW_WANTransition', 'archived' => false, 'time' => 1794319200000]]]),
    ]);

    $events = Unifi::classic()->events()->recent(24, 0, 50);
    $alarms = Unifi::classic()->events()->alarms(false, 'EVT_GW_WANTransition');

    expect($events[0]->key)->toBe('EVT_WU_Connected')
        ->and($events[0]->time?->toIso8601ZuluString())->toBe('2026-11-10T14:00:00Z')
        ->and($alarms[0]->archived)->toBeFalse();

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/stat/event')
        && $request->data() === ['_sort' => '-time', 'within' => 24, 'type' => null, '_start' => 0, '_limit' => 50]);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/list/alarm')
        && $request->data() === ['archived' => false, 'key' => 'EVT_GW_WANTransition']);
});

it('archives alarms', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->events()->archiveAlarm();
    Unifi::classic()->events()->archiveAlarm('a1');

    Http::assertSent(fn(Request $request): bool => $request->data() === ['cmd' => 'archive-all-alarms']);
    Http::assertSent(fn(Request $request): bool => $request->data() === ['cmd' => 'archive-alarm', '_id' => 'a1']);
});

it('restarts devices and validates mac addresses', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->devices()->restart(['94:2A:6F:26:C6:CA'], 'hard');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/cmd/devmgr')
        && $request->data() === ['cmd' => 'restart', 'macs' => ['94:2a:6f:26:c6:ca'], 'reboot_type' => 'hard']);
    expect(fn() => Unifi::classic()->devices()->locate('not-a-mac'))->toThrow(InvalidArgumentException::class);
});

it('updates wlan settings with put', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    Unifi::classic()->wlans()->setPassphrase('wlan1', 'NewGuest2026!');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/s/default/rest/wlanconf/wlan1')
        && $request->data() === ['x_passphrase' => 'NewGuest2026!']);
    expect(fn() => Unifi::classic()->wlans()->setPassphrase('wlan1', 'short'))->toThrow(InvalidArgumentException::class);
});

it('raises the meta message of classic errors', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'error', 'msg' => 'api.err.InvalidPayload'], 'data' => []], 400)]);

    try {
        Unifi::classic()->clients()->unblock('aa:bb:cc:dd:ee:01');
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe('api.err.InvalidPayload')->and($exception->httpStatus())->toBe(400);
    }

    Http::assertSentCount(2);
});

it('detects error envelopes returned with http 200', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'error', 'msg' => 'api.err.UnknownStation'], 'data' => []])]);

    expect(fn() => Unifi::classic()->clients()->reconnect('aa:bb:cc:dd:ee:01'))->toThrow(ApiException::class, 'api.err.UnknownStation');
});

it('logs out and forgets the session', function (): void {
    Http::fake(unifiOsLogin() + ['*' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []])]);

    $classic = Unifi::classic();
    $classic->login();
    $classic->logout();

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '/api/auth/logout' && $request->hasHeader('X-CSRF-Token', 'csrf-from-header'));
    expect(Cache::get('unifi:session:' . hash('sha256', 'default|https://192.168.1.1|api-user')))->toBeNull();
});

it('requires credentials for the classic api', function (): void {
    expect(fn() => Unifi::connection('cloud')->classic()->sites()->all())->toThrow(InvalidConfiguration::class, 'UNIFI_USERNAME');
});
