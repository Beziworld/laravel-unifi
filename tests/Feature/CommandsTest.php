<?php

declare(strict_types=1);

use Beziworld\Unifi\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('checks both apis of a connection', function (): void {
    Http::fake([
        TestCase::API . '/v1/info' => Http::response(['applicationVersion' => '9.1.120']),
        TestCase::API . '/v1/sites*' => Http::response(TestCase::page([['id' => TestCase::SITE, 'internalReference' => 'default', 'name' => 'Default']])),
        TestCase::URL . '/api/auth/login' => Http::response([], 200, ['Set-Cookie' => 'TOKEN=' . TestCase::jwt(['csrfToken' => 'c']) . '; path=/']),
        TestCase::URL . '/proxy/network/api/self/sites' => Http::response(['meta' => ['rc' => 'ok'], 'data' => [['name' => 'default']]]),
    ]);

    $this->artisan('unifi:check')
        ->expectsOutputToContain('Network 9.1.120')
        ->expectsOutputToContain('UniFi OS, 1 site(s)')
        ->assertSuccessful();
});

it('fails the check when the key is rejected', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $this->artisan('unifi:check', ['connection' => 'cloud'])->assertFailed();
});

it('generates vouchers from the console', function (): void {
    Http::fake(['*' => Http::response(['vouchers' => [jsonFixture('voucher')]], 201)]);

    $this->artisan('unifi:vouchers', ['name' => 'Room 5', '--count' => '1', '--minutes' => '720', '--guests' => '0'])
        ->expectsOutputToContain('48614-09510')
        ->assertSuccessful();

    Http::assertSent(fn(Request $request): bool => $request->data() === ['name' => 'Room 5', 'count' => 1, 'timeLimitMinutes' => 720]);
});

it('rejects invalid voucher options', function (): void {
    $this->artisan('unifi:vouchers', ['name' => 'Room 5', '--minutes' => '0'])->assertExitCode(2);
    Http::assertNothingSent();
});
