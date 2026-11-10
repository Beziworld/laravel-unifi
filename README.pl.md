# Laravel UniFi

[![tests](https://github.com/beziworld/laravel-unifi/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-unifi/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-unifi.svg)](https://packagist.org/packages/beziworld/laravel-unifi)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-unifi.svg)](LICENSE.md)

Klient Laravel dla aplikacji UniFi Network firmy Ubiquiti: lokalizacje (sites), urządzenia, podłączeni klienci,
autoryzacja gości w hotspocie, vouchery, sieci, rozgłaszane sieci Wi-Fi, zdarzenia i alarmy, dla jednego lub wielu
kontrolerów.

[English documentation](README.md)

## Funkcje

- **Oficjalne UniFi Network API** (klucz API, `/proxy/network/integration/v1/...`), lokalnie lub przez chmurowy
  konektor Ubiquiti (`api.ui.com`)
- **Klasyczne API kontrolera** (logowanie sesyjne) dla tego, czego oficjalne API nie oferuje: zdarzenia, alarmy,
  blokowanie i ponowne łączenie klientów, hasła WLAN, kontrolery starsze niż Network 9
- Konsole UniFi OS (Cloud Gateway, UDM, Cloud Key Gen2+) i samodzielnie hostowane kontrolery: ścieżka logowania,
  ciasteczko, token CSRF i prefiks `/proxy/network` obsługiwane automatycznie
- Wiele nazwanych kontrolerów oraz `using([...])` dla kontrolerów zapisanych w bazie danych
- Certyfikaty self-signed per połączenie: `verify_ssl` true, false albo ścieżka do pakietu CA
- Vouchery hotspotu z walidacją według schematu, `forStay()` dla voucherów ważnych do wymeldowania gościa
- Typowane DTO (`Site`, `Device`, `ConnectedClient`, `Voucher`, `Network`, `WifiBroadcast`, `Event`, ...) z
  zachowanym surowym payloadem i datami `CarbonImmutable` w UTC
- Paginacja obiektami `Page`, leniwe przechodzenie po wszystkich stronach, builder `Filter` dla oficjalnej
  składni filtrów
- Sesje klasycznego API w cache Laravela, odnawiane raz po HTTP 401
- Odczyty ponawiane przy błędach połączenia i HTTP 5xx, zapisy nigdy, HTTP 429 jako `RateLimitException` z
  `retryAfter()`
- `php artisan unifi:check` i `php artisan unifi:vouchers`
- PHP 8.3+, Laravel 12 i 13, PHPStan poziom 8, działa z `Http::fake()`

## Dlaczego ten pakiet

Packagist (listopad 2026) pokazuje `art-of-wifi/unifi-api-client` z ok. 449 tys. pobrań: klienta niezależnego od
frameworka, opartego na curl, obsługującego tylko klasyczne API, trzymającego ciasteczka w sesji PHP i zwracającego
`stdClass` albo `false`. Istnieje też jego odpowiednik dla oficjalnego API
(`art-of-wifi/unifi-network-application-api-client`, oparty na Saloon) i kilka małych klientów, ale nie ma pakietu
dla Laravela. Ten pakiet dodaje:

- oba API za jedną fasadą Laravela, z plikiem konfiguracji, nazwanymi połączeniami i danymi podawanymi w locie
- klienta HTTP Laravela, dzięki czemu w testach działają `Http::fake()` i `Http::assertSent()`
- typowane wyjątki zamiast `false` i `trigger_error()`
- sesje w cache Laravela (współdzielone przez workery kolejek) zamiast `$_SESSION`
- pomocniki pod hotele: voucher na pobyt, lista gości, czyszczenie wygasłych voucherów, generowanie z konsoli

## Instalacja

```bash
composer require beziworld/laravel-unifi
php artisan vendor:publish --tag=unifi-config   # opcjonalnie
```

```dotenv
UNIFI_URL=https://192.168.1.1            # konsola UniFi OS albo https://unifi.example.com:8443 dla starszego kontrolera
UNIFI_API_KEY=                           # Network > Settings > Control Plane > Integrations
UNIFI_SITE=default                       # krótka nazwa lokalizacji; jej UUID jest pobierany jednorazowo
UNIFI_SITE_ID=                           # opcjonalnie: UUID lokalizacji, pomija wyszukiwanie
UNIFI_VERIFY_SSL=true                    # false albo ścieżka do pakietu CA dla certyfikatu self-signed konsoli

# Tylko dla klasycznego API (zdarzenia, alarmy, starsze kontrolery):
UNIFI_USERNAME=api-user                  # konto lokalne bez uwierzytelniania dwuskładnikowego
UNIFI_PASSWORD=
UNIFI_CONTROLLER=auto                    # auto, unifi_os albo legacy

# Przez chmurowy konektor zamiast adresu lokalnego (klucz API Site Managera):
UNIFI_CONSOLE_ID=
```

```bash
php artisan unifi:check
```

Kolejne kontrolery (np. jeden na hotel) dodaje się w `config/unifi.php`:

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

## Użycie

### Vouchery Wi-Fi dla gości hotelowych

```php
use Beziworld\Unifi\Facades\Unifi;

// Jeden voucher na dwa urządzenia, ważny do wymeldowania (np. z rezerwacji w PMS)
$voucher = Unifi::connection('hotel-krakow')->vouchers()->forStay(
    name: 'Pokój 204 / Kowalski',
    checkout: $reservation->departure->setTime(11, 0),
    devices: 2,
);

$voucher->formattedCode(); // "48614-09510"

// Paczki z limitami
use Beziworld\Unifi\Data\VoucherRequest;

$vouchers = Unifi::vouchers()->create(new VoucherRequest(
    name: 'Dzień konferencji',
    timeLimitMinutes: 600,
    count: 50,
    authorizedGuestLimit: 1,
    dataUsageLimitMBytes: 2048,
    rxRateLimitKbps: 20000,
    txRateLimitKbps: 5000,
));

Unifi::vouchers()->findByCode('48614-09510');
Unifi::vouchers()->purgeExpired();                           // zwraca liczbę usuniętych voucherów
```

```bash
php artisan unifi:vouchers "Pokój 204" --minutes=4320 --guests=2 --connection=hotel-krakow
```

### Autoryzacja gości (hotspot)

```php
$client = Unifi::clients()->findByMac('aa:bb:cc:dd:ee:01');

$result = Unifi::clients()->authorizeGuest($client->id, minutes: 1440, megabytes: 2048, downKbps: 10000, upKbps: 2000);
$result->granted->expiresAt;                                  // CarbonImmutable (UTC)

Unifi::clients()->unauthorizeGuest($client->id);

foreach (Unifi::clients()->guests() as $guest) {              // LazyCollection po wszystkich stronach
    $guest->isAuthorizedGuest();
}
```

### Lokalizacje, urządzenia i statystyki

```php
Unifi::info()->applicationVersion;                            // "9.1.120"
Unifi::sites()->all();                                        // LazyCollection<Site>
Unifi::forSite('hotel2')->devices()->list();                  // krótka nazwa albo UUID

foreach (Unifi::devices()->all() as $device) {
    if (! $device->isOnline()) {
        // alert
    }
}

Unifi::devices()->statistics($deviceId)->cpuUtilizationPct;
Unifi::devices()->restart($deviceId);
Unifi::devices()->powerCyclePort($switchId, portIdx: 7);     // restart access pointa zasilanego PoE
Unifi::devices()->pending();                                  // urządzenia czekające na adopcję
Unifi::devices()->adopt('94:2a:6f:26:c6:ca');
```

### Sieci i rozgłaszane sieci Wi-Fi

```php
Unifi::networks()->list()->items;                             // Network (name, management, vlanId, ...)
Unifi::networks()->wans();                                    // ['uuid' => 'Internet 1']
Unifi::wifi()->all();                                         // WifiBroadcast (SSID)
Unifi::wifi()->disable($broadcastId);                         // odczytuje definicję i odsyła ją ze zmianą
Unifi::wifi()->update($broadcastId, $definition);            // pełna definicja według oficjalnego schematu
```

### Filtry i paginacja

```php
use Beziworld\Unifi\Support\Filter;

$page = Unifi::vouchers()->list(
    Filter::and(Filter::eq('expired', false), Filter::like('name', 'Pokój*')),
    offset: 0,
    limit: 100,
);

$page->items; $page->totalCount; $page->hasMore(); $page->nextOffset();
```

### Klasyczne API: zdarzenia, alarmy, blokowanie, hasło WLAN

```php
$classic = Unifi::classic();                                  // skonfigurowana lokalizacja albo Unifi::classic('hotel2')

$classic->events()->recent(withinHours: 24, limit: 100);     // list<Event>
$classic->events()->alarms(archived: false);
$classic->events()->archiveAlarm($alarmId);

$classic->clients()->online();                                // list<ClassicClient>
$classic->clients()->block('aa:bb:cc:dd:ee:01');
$classic->clients()->reconnect('aa:bb:cc:dd:ee:01');
$classic->clients()->authorizeGuest('aa:bb:cc:dd:ee:01', minutes: 1440, downKbps: 10000);

$classic->vouchers()->create(minutes: 1440, count: 10, quota: 1, note: 'Recepcja');
$classic->wlans()->setPassphrase($wlanId, 'Gosc-' . now()->format('dmy'));
$classic->devices()->restart(['94:2a:6f:26:c6:ca']);
$classic->sites()->health();
```

Klasyczne API nie ma oficjalnej dokumentacji Ubiquiti; endpointy odpowiadają wieloletniej dokumentacji
społeczności. Dostępność `stat/event` zależy od wersji Network.

### Kontrolery zapisane w bazie danych

```php
Unifi::using([
    'url' => $hotel->unifi_url,
    'api_key' => decrypt($hotel->unifi_api_key),
    'site' => $hotel->unifi_site,
    'verify_ssl' => false,
])->vouchers()->forStay('Pokój 1', $checkout);
```

### Pozostałe endpointy

```php
// Dowolny endpoint oficjalnego API; {siteId} jest zastępowane UUID bieżącej lokalizacji
Unifi::request('GET', '/v1/sites/{siteId}/firewall/policies');
Unifi::request('POST', '/v1/sites/{siteId}/acl-rules', body: [...]);

// Dowolny klasyczny endpoint bieżącej lokalizacji
Unifi::classic()->request('GET', 'stat/health');
```

## Wyjątki

Wszystkie wyjątki dziedziczą po `Beziworld\Unifi\Exceptions\UnifiException`:

| Wyjątek | Kiedy |
|---|---|
| `AuthenticationException` | Odrzucony klucz API albo logowanie (HTTP 401/403). Logowanie nigdy nie jest ponawiane automatycznie. |
| `RateLimitException` | HTTP 429; `retryAfter()` zwraca sekundy z nagłówka `Retry-After` |
| `ApiException` | Odpowiedź z błędem albo `meta.rc = error`; `errorCode()` (np. `api.err.InvalidPayload`), `httpStatus()`, `requestId()`, `payload()` |
| `ConnectionFailed` | Błąd DNS, TCP, TLS albo przekroczenie czasu (po ponowieniach odczytu) |
| `SiteNotFound` | Żadna lokalizacja nie odpowiada `UNIFI_SITE` |
| `InvalidConfiguration` | Nieznane połączenie, brak adresu lub danych logowania, brak klucza API dla oficjalnego API |

## Uwagi

- Dla klasycznego API utwórz osobne konto lokalne, a dla oficjalnego API osobny klucz.
- UniFi OS blokuje klientów po wielokrotnych nieudanych logowaniach; pakiet nigdy nie ponawia odrzuconego logowania.
- Wyłączenie weryfikacji TLS ujawnia dane logowania każdemu na trasie sieciowej. Lepiej wskazać pakiet CA.
- Wywołania są synchroniczne; paczki voucherów i akcje na urządzeniach najlepiej wykonywać w zadaniach kolejki.

## Rozwój

```bash
composer install
composer ci   # pint --test, phpstan (poziom 8), pest
```

## Autor

Pakiet tworzy i utrzymuje **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia i propozycje: [GitHub issues](https://github.com/beziworld/laravel-unifi/issues)
- Podatności bezpieczeństwa: zobacz [SECURITY.md](SECURITY.md)

Potrzebujesz integracji? Skontaktuj się przez [beziworld.eu](https://beziworld.eu).

### Inne pakiety autora

- [beziworld/laravel-previo](https://github.com/beziworld/laravel-previo): hotelowy PMS Previo
- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): panel hostingowy DirectAdmin
- [beziworld/laravel-proxmox](https://github.com/beziworld/laravel-proxmox): Proxmox VE

## Licencja

MIT. Zobacz [LICENSE.md](LICENSE.md). Pakiet nie jest powiązany z Ubiquiti Inc., ani przez nią wspierany czy
sponsorowany. UniFi jest znakiem towarowym Ubiquiti Inc.
