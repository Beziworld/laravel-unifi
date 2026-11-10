# Laravel UniFi

[![tests](https://github.com/beziworld/laravel-unifi/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-unifi/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-unifi.svg)](https://packagist.org/packages/beziworld/laravel-unifi)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-unifi.svg)](LICENSE.md)

Laravel client for the UniFi Network application by Ubiquiti: sites, devices, connected clients, hotspot guest
authorization, vouchers, networks, Wi-Fi broadcasts, events and alarms, for one or many controllers.

[Polska wersja dokumentacji](README.pl.md)

## Features

- The **official UniFi Network API** (API key, `/proxy/network/integration/v1/...`), locally or through the
  Ubiquiti cloud connector (`api.ui.com`)
- The **classic controller API** (session login) for what the official API does not offer: events, alarms,
  blocking and reconnecting clients, WLAN passphrases, controllers older than Network 9
- UniFi OS consoles (Cloud Gateway, UDM, Cloud Key Gen2+) and self-hosted legacy controllers: login path, cookie,
  CSRF token and `/proxy/network` prefix handled automatically
- Multiple named controllers plus `using([...])` for controllers stored in your database
- Self-signed TLS per connection: `verify_ssl` true, false or a CA bundle path
- Hotspot vouchers with schema validation, `forStay()` for vouchers valid until a hotel check-out
- Typed DTOs (`Site`, `Device`, `ConnectedClient`, `Voucher`, `Network`, `WifiBroadcast`, `Event`, ...) that keep
  the raw payload, UTC `CarbonImmutable` dates
- Pagination with `Page` objects and lazy iteration over all pages, a `Filter` builder for the official filter
  syntax
- Classic API sessions cached in Laravel's cache and renewed once on HTTP 401
- Reads retried on connection errors and HTTP 5xx, writes never retried, HTTP 429 as `RateLimitException` with
  `retryAfter()`
- `php artisan unifi:check` and `php artisan unifi:vouchers`
- PHP 8.3+, Laravel 12 and 13, PHPStan level 8, works with `Http::fake()`

## Why this package

Packagist (November 2026) lists `art-of-wifi/unifi-api-client` with about 449k downloads: a framework-agnostic,
curl-based client for the classic API only, which keeps cookies in the PHP session and returns `stdClass` or
`false`. Its official-API sibling `art-of-wifi/unifi-network-application-api-client` (Saloon based) and a few
small clients exist, but there is no Laravel package. This package adds:

- both APIs behind one Laravel facade, with config files, named connections and runtime credentials
- Laravel's HTTP client, so `Http::fake()` and `Http::assertSent()` work in your tests
- typed exceptions instead of `false` and `trigger_error()`
- sessions in the Laravel cache (shared by queue workers) instead of `$_SESSION`
- hotel-oriented helpers: vouchers for a stay, guest lists, voucher clean-up, console voucher generation

## Installation

```bash
composer require beziworld/laravel-unifi
php artisan vendor:publish --tag=unifi-config   # optional
```

```dotenv
UNIFI_URL=https://192.168.1.1            # UniFi OS console, or https://unifi.example.com:8443 for a legacy controller
UNIFI_API_KEY=                           # Network > Settings > Control Plane > Integrations
UNIFI_SITE=default                       # short site name; its UUID is looked up once
UNIFI_SITE_ID=                           # optional: site UUID, skips the lookup
UNIFI_VERIFY_SSL=true                    # false or a CA bundle path for the self-signed console certificate

# Only for the classic API (events, alarms, legacy controllers):
UNIFI_USERNAME=api-user                  # a local account without two-factor authentication
UNIFI_PASSWORD=
UNIFI_CONTROLLER=auto                    # auto, unifi_os or legacy

# Through the cloud connector instead of the local address (Site Manager API key):
UNIFI_CONSOLE_ID=
```

```bash
php artisan unifi:check
```

Several controllers (for example one per hotel) go into `config/unifi.php`:

```php
'connections' => [
    'default' => [...],
    'hotel-krakow' => [
        'url' => 'https://10.20.0.1',
        'api_key' => env('UNIFI_KRAKOW_API_KEY'),
        'site' => 'default',
        'verify_ssl' => storage_path('certs/unifi-krakow-ca.pem'),
    ],
],
```

## Usage

### Guest Wi-Fi vouchers for a hotel stay

```php
use Beziworld\Unifi\Facades\Unifi;

// One voucher for two devices, valid until check-out (e.g. from a PMS reservation)
$voucher = Unifi::connection('hotel-krakow')->vouchers()->forStay(
    name: 'Room 204 / Smith',
    checkout: $reservation->departure->setTime(11, 0),
    devices: 2,
);

$voucher->formattedCode(); // "48614-09510"

// Batches with limits
use Beziworld\Unifi\Data\VoucherRequest;

$vouchers = Unifi::vouchers()->create(new VoucherRequest(
    name: 'Conference day',
    timeLimitMinutes: 600,
    count: 50,
    authorizedGuestLimit: 1,
    dataUsageLimitMBytes: 2048,
    rxRateLimitKbps: 20000,
    txRateLimitKbps: 5000,
));

Unifi::vouchers()->findByCode('48614-09510');
Unifi::vouchers()->purgeExpired();                           // returns the number of deleted vouchers
```

```bash
php artisan unifi:vouchers "Room 204" --minutes=4320 --guests=2 --connection=hotel-krakow
```

### Guest authorization (hotspot)

```php
$client = Unifi::clients()->findByMac('aa:bb:cc:dd:ee:01');

$result = Unifi::clients()->authorizeGuest($client->id, minutes: 1440, megabytes: 2048, downKbps: 10000, upKbps: 2000);
$result->granted->expiresAt;                                  // CarbonImmutable (UTC)

Unifi::clients()->unauthorizeGuest($client->id);

foreach (Unifi::clients()->guests() as $guest) {              // LazyCollection over all pages
    $guest->isAuthorizedGuest();
}
```

### Sites, devices and statistics

```php
Unifi::info()->applicationVersion;                            // "9.1.120"
Unifi::sites()->all();                                        // LazyCollection<Site>
Unifi::forSite('hotel2')->devices()->list();                  // short name or UUID

foreach (Unifi::devices()->all() as $device) {
    if (! $device->isOnline()) {
        // alert
    }
}

Unifi::devices()->statistics($deviceId)->cpuUtilizationPct;
Unifi::devices()->restart($deviceId);
Unifi::devices()->powerCyclePort($switchId, portIdx: 7);     // reboot a PoE access point
Unifi::devices()->pending();                                  // devices waiting for adoption
Unifi::devices()->adopt('94:2a:6f:26:c6:ca');
```

### Networks and Wi-Fi broadcasts

```php
Unifi::networks()->list()->items;                             // Network (name, management, vlanId, ...)
Unifi::networks()->wans();                                    // ['uuid' => 'Internet 1']
Unifi::wifi()->all();                                         // WifiBroadcast (SSIDs)
Unifi::wifi()->disable($broadcastId);                         // reads the definition and sends it back
Unifi::wifi()->update($broadcastId, $definition);            // full definition, as in the official schema
```

### Filters and pagination

```php
use Beziworld\Unifi\Support\Filter;

$page = Unifi::vouchers()->list(
    Filter::and(Filter::eq('expired', false), Filter::like('name', 'Room*')),
    offset: 0,
    limit: 100,
);

$page->items; $page->totalCount; $page->hasMore(); $page->nextOffset();
```

### Classic API: events, alarms, blocking, WLAN passphrase

```php
$classic = Unifi::classic();                                  // configured site, or Unifi::classic('hotel2')

$classic->events()->recent(withinHours: 24, limit: 100);     // list<Event>
$classic->events()->alarms(archived: false);
$classic->events()->archiveAlarm($alarmId);

$classic->clients()->online();                                // list<ClassicClient>
$classic->clients()->block('aa:bb:cc:dd:ee:01');
$classic->clients()->reconnect('aa:bb:cc:dd:ee:01');
$classic->clients()->authorizeGuest('aa:bb:cc:dd:ee:01', minutes: 1440, downKbps: 10000);

$classic->vouchers()->create(minutes: 1440, count: 10, quota: 1, note: 'Lobby');
$classic->wlans()->setPassphrase($wlanId, 'Guest-' . now()->format('dmy'));
$classic->devices()->restart(['94:2a:6f:26:c6:ca']);
$classic->sites()->health();
```

The classic API is not officially documented by Ubiquiti; endpoints follow the long-standing community
documentation. Availability of `stat/event` depends on the Network version.

### Controllers stored in the database

```php
Unifi::using([
    'url' => $hotel->unifi_url,
    'api_key' => decrypt($hotel->unifi_api_key),
    'site' => $hotel->unifi_site,
    'verify_ssl' => false,
])->vouchers()->forStay('Room 1', $checkout);
```

### Anything else

```php
// Any endpoint of the official API; {siteId} is replaced with the current site UUID
Unifi::request('GET', '/v1/sites/{siteId}/firewall/policies');
Unifi::request('POST', '/v1/sites/{siteId}/acl-rules', body: [...]);

// Any classic endpoint of the current site
Unifi::classic()->request('GET', 'stat/health');
```

## Exceptions

All exceptions extend `Beziworld\Unifi\Exceptions\UnifiException`:

| Exception | When |
|---|---|
| `AuthenticationException` | API key or login rejected (HTTP 401/403). Logins are never repeated automatically. |
| `RateLimitException` | HTTP 429; `retryAfter()` returns the seconds from `Retry-After` |
| `ApiException` | Error response or `meta.rc = error`; `errorCode()` (e.g. `api.err.InvalidPayload`), `httpStatus()`, `requestId()`, `payload()` |
| `ConnectionFailed` | DNS, TCP, TLS or timeout error (after read retries) |
| `SiteNotFound` | No site matches `UNIFI_SITE` |
| `InvalidConfiguration` | Unknown connection, missing URL or credentials, missing API key for the official API |

## Notes

- Create a dedicated local account for the classic API and a dedicated API key for the official API.
- UniFi OS blocks clients after repeated failed logins; this package never retries a rejected login.
- Disabling TLS verification exposes credentials to anyone on the network path. Prefer a CA bundle path.
- Calls are synchronous; voucher batches and device actions belong in queued jobs.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues and feature requests: [GitHub issues](https://github.com/beziworld/laravel-unifi/issues)
- Security vulnerabilities: see [SECURITY.md](SECURITY.md)

Need an integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

- [beziworld/laravel-previo](https://github.com/beziworld/laravel-previo): Previo hotel PMS
- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): DirectAdmin hosting panel
- [beziworld/laravel-proxmox](https://github.com/beziworld/laravel-proxmox): Proxmox VE

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with, endorsed by or sponsored by Ubiquiti Inc.
UniFi is a trademark of Ubiquiti Inc.
