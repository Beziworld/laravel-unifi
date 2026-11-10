<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | Name of the controller used by the Unifi facade when no connection is
    | selected explicitly with Unifi::connection('name').
    |
    */

    'default' => env('UNIFI_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Controllers
    |--------------------------------------------------------------------------
    |
    | "url" is the address of the console or controller, e.g.
    | https://192.168.1.1 (UniFi OS: Cloud Gateway, UDM, Cloud Key Gen2) or
    | https://unifi.example.com:8443 (self-hosted legacy controller).
    |
    | "api_key" authenticates the official UniFi Network API (create it in
    | Network > Settings > Control Plane > Integrations). "username" and
    | "password" (a local account, without two-factor authentication) are
    | used by the classic controller API, which covers events, alarms and
    | the features that the official API does not expose yet.
    |
    | "console_id" sends official API calls through the Ubiquiti cloud
    | connector (https://api.ui.com) instead of the local address; the
    | "api_key" must then be a Site Manager API key.
    |
    | "site_id" is the UUID of the site for the official API. When it is
    | empty, the package looks up the site whose internal reference equals
    | "site" (the short name used by the classic API, "default" for the
    | first site).
    |
    | "controller" selects the classic API flavour: "auto" detects it,
    | "unifi_os" or "legacy" skip the detection request.
    |
    | "verify_ssl" may be true, false or a path to a CA bundle. Consoles ship
    | with self-signed certificates: prefer a CA bundle or a trusted
    | certificate, and disable verification only on trusted networks.
    |
    */

    'connections' => [

        'default' => [
            'url' => env('UNIFI_URL'),
            'api_key' => env('UNIFI_API_KEY'),
            'username' => env('UNIFI_USERNAME'),
            'password' => env('UNIFI_PASSWORD'),
            'site' => env('UNIFI_SITE', 'default'),
            'site_id' => env('UNIFI_SITE_ID'),
            'console_id' => env('UNIFI_CONSOLE_ID'),
            'controller' => env('UNIFI_CONTROLLER', 'auto'),
            'verify_ssl' => env('UNIFI_VERIFY_SSL', true),
            'timeout' => (int) env('UNIFI_TIMEOUT', 30),
            'connect_timeout' => (int) env('UNIFI_CONNECT_TIMEOUT', 10),
            'retries' => (int) env('UNIFI_RETRIES', 2),
            'retry_delay' => (int) env('UNIFI_RETRY_DELAY', 300),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Classic API sessions
    |--------------------------------------------------------------------------
    |
    | Session cookies and CSRF tokens of the classic API are kept in the
    | cache, so that queued jobs and requests reuse one login instead of
    | authenticating on every call. An expired session is renewed once
    | automatically.
    |
    */

    'session' => [
        'store' => env('UNIFI_SESSION_STORE'),
        'prefix' => 'unifi:session:',
        'ttl' => (int) env('UNIFI_SESSION_TTL', 3600),
    ],

];
