# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Official UniFi Network API client (API key, local console or Ubiquiti cloud connector): application info, sites, adopted and pending devices with statistics, device restart and PoE port power cycling, device adoption and removal, connected clients, guest authorization and revocation, hotspot vouchers (create, list, find by code, delete, bulk delete by filter), networks and WAN interfaces, Wi-Fi broadcasts.
- Classic controller API client for UniFi OS consoles and legacy controllers: session login with cached cookies and CSRF tokens, sites and health, clients (online, known, guests, block, unblock, reconnect, forget), guest authorization, vouchers, devices (restart, adopt, locate), WLAN configuration and passphrase, networks, events and alarms.
- Multiple named controllers, runtime credentials with `using()`, per-connection TLS verification setting.
- Pagination objects, lazy iteration over all pages and a builder for the official filter syntax.
- `VoucherRequest::untilCheckout()` and `vouchers()->forStay()` for vouchers valid until a hotel check-out.
- Typed exceptions for authentication, rate limits (Retry-After), API errors, connection failures and unknown sites.
- `unifi:check` and `unifi:vouchers` console commands.
